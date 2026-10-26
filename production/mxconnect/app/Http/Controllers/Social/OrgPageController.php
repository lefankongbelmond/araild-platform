<?php

namespace App\Http\Controllers\Social;

use App\Models\Central\Country;
use App\Models\Central\Mutual;
use App\Models\Social\MutualProfile;
use App\Models\Social\PostMedia;
use App\Services\Social\FeedService;
use App\Services\Social\MediaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Pages créées par les membres (comme une page Facebook) : association,
 * établissement de santé, partenaire, organisation communautaire…
 *
 * Une page est une fiche d'organisation du réseau SANS espace de gestion ni
 * base de données : on peut la suivre, elle publie dans le fil, on peut la
 * mentionner. Elle n'est jamais « vérifiée » d'office : le badge reste réservé
 * aux organisations validées par l'administration du réseau.
 */
class OrgPageController extends SocialController
{
    public function __construct(FeedService $feed, protected MediaService $media)
    {
        parent::__construct($feed);
    }

    public function mine()
    {
        $pages = \App\Services\Social\PageService::managedBy($this->viewer())->with(['profile', 'country'])->orderBy('name')->get();

        return view('social.pages.index', ['pages' => $pages, 'max' => $this->maxPages()]);
    }

    public function create()
    {
        if ($this->ownedQuery()->count() >= $this->maxPages()) {
            return redirect()->route('social.pages.mine')->with('error', 'Vous gérez déjà le nombre maximal de pages (' . $this->maxPages() . ').');
        }

        return view('social.pages.form', ['mutual' => new Mutual(), 'profile' => new MutualProfile(['category' => 'osc']),
            'countries' => $this->countries(), 'editing' => false]);
    }

    public function store(Request $request)
    {
        abort_if($this->ownedQuery()->count() >= $this->maxPages(), 422, 'Nombre maximal de pages atteint.');
        $data = $this->validated($request, true);
        $viewer = $this->viewer();
        $country = Country::findOrFail($data['country_id']);

        $mutual = DB::connection('central')->transaction(function () use ($data, $viewer, $country) {
            // Sans événements : aucune base de données ni sous-domaine n'est créé pour une page.
            $mutual = Mutual::withoutEvents(fn () => Mutual::create([
                'id' => $this->newId(), 'name' => $data['name'], 'slug' => $this->uniqueSlug($data['name']),
                'country_id' => $country->id, 'currency_id' => $country->default_currency_id, 'locale_id' => $country->default_locale_id,
                'region' => $data['region'] ?? null, 'city' => $data['city'] ?? null, 'status' => 'pending',
            ]));
            MutualProfile::create(['mutual_id' => $mutual->id, 'owner_account_id' => $viewer->id,
                'domains' => \App\Support\SocialDomains::clean($data['domains'] ?? []) ?: null]
                + collect($data)->only(['category', 'tagline', 'description', 'phone', 'email', 'website', 'address'])->all());
            \App\Models\Social\PageAdmin::create(['mutual_id' => $mutual->id, 'public_account_id' => $viewer->id, 'role' => 'admin']);

            return $mutual;
        });
        $this->saveImages($request, $mutual);
        cache()->forget('social:places');

        return redirect()->route('social.mutual', $mutual->slug)->with('ok', 'Votre page « ' . $mutual->name . ' » est créée. Publiez votre première actualité !');
    }

    public function edit(string $slug)
    {
        $mutual = $this->ownedPage($slug);

        $managers = \App\Models\Social\PageAdmin::where('mutual_id', $mutual->id)->with('account')->orderBy('created_at')->get();

        return view('social.pages.form', ['mutual' => $mutual, 'profile' => $mutual->profile, 'countries' => $this->countries(), 'editing' => true, 'managers' => $managers]);
    }

    public function update(Request $request, string $slug)
    {
        $mutual = $this->ownedPage($slug);
        $data = $this->validated($request, false);

        Mutual::withoutEvents(fn () => $mutual->update([
            'name' => $data['name'], 'country_id' => $data['country_id'],
            'region' => $data['region'] ?? null, 'city' => $data['city'] ?? null,
        ]));
        $mutual->profile->update(collect($data)->only(['category', 'tagline', 'description', 'phone', 'email', 'website', 'address'])->all()
            + ['domains' => \App\Support\SocialDomains::clean($data['domains'] ?? []) ?: null]);
        $this->saveImages($request, $mutual);
        cache()->forget('social:places');

        return redirect()->route('social.mutual', $mutual->slug)->with('ok', 'Page mise à jour.');
    }

    /** Supprime la page, ses publications et leurs fichiers. */
    public function destroy(Request $request, string $slug)
    {
        $mutual = $this->ownedPage($slug);
        $request->validate(['confirm_name' => ['required', Rule::in([$mutual->name])]],
            ['confirm_name.in' => 'Recopiez exactement le nom de la page pour confirmer.', 'confirm_name.required' => 'Recopiez le nom de la page pour confirmer.']);

        $postIds = \App\Models\Social\Post::withTrashed()->where('mutual_id', $mutual->id)->pluck('id');
        $files = PostMedia::whereIn('post_id', $postIds)->get()->flatMap(fn ($m) => [$m->path, $m->thumb_path])
            ->merge(\App\Models\Social\Post::withTrashed()->whereIn('id', $postIds)->pluck('link_image_path'))
            ->push($mutual->profile?->logo_path, $mutual->profile?->cover_path)
            ->merge(\App\Models\Social\Project::withTrashed()->where('mutual_id', $mutual->id)->pluck('cover_path'))
            ->filter()->all();
        $name = $mutual->name;
        Mutual::withoutEvents(fn () => $mutual->delete());   // suppression en cascade (profil, publications, abonnés, projets, offres, demandes)
        $this->media->delete(...$files);
        \Illuminate\Support\Facades\Storage::disk('social_private')->deleteDirectory("requests/{$mutual->id}");   // CV reçus
        cache()->forget('social:places');

        return redirect()->route('social.pages.mine')->with('ok', "La page « {$name} » a été supprimée.");
    }

    /** Ajoute un gestionnaire (téléphone ou e-mail de son compte MX-CONNECT). */
    public function addManager(Request $request, string $slug)
    {
        $mutual = $this->ownedPage($slug);
        $data = $request->validate(['manager' => ['required', 'string', 'max:180'], 'role' => ['required', Rule::in(array_keys(\App\Models\Social\PageAdmin::ROLES))]],
            ['manager.required' => 'Indiquez le téléphone ou l’e-mail du compte à ajouter.']);
        $admin = \App\Services\Social\PageService::addManager($mutual, $data['manager'], $data['role'], $this->viewer());

        return redirect()->route('social.pages.edit', $mutual->slug)->with('ok', $admin->account->name . ' peut maintenant publier au nom de la page.');
    }

    public function removeManager(string $slug, int $admin)
    {
        $mutual = $this->ownedPage($slug);
        $row = \App\Models\Social\PageAdmin::where('mutual_id', $mutual->id)->findOrFail($admin);
        abort_if($row->public_account_id === (int) $mutual->profile->owner_account_id, 422, 'Le créateur de la page ne peut pas être retiré.');
        $row->delete();

        return redirect()->route('social.pages.edit', $mutual->slug)->with('ok', 'Gestionnaire retiré.');
    }

    // ---

    private function validated(Request $request, bool $creating): array
    {
        $maxKb = (int) config('mxconnect.social.max_upload_kb', 6144);

        return $request->validate([
            'name'        => ['required', 'string', 'min:3', 'max:120'],
            'category'    => ['required', Rule::in(array_keys(MutualProfile::CATEGORIES))],
            'country_id'  => ['required', 'integer', Rule::exists('central.countries', 'id')->where('active', true)],
            'region'      => ['nullable', 'string', 'max:120'],
            'city'        => ['nullable', 'string', 'max:120'],
            'tagline'     => ['nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'phone'       => ['nullable', 'string', 'max:40'],
            'email'       => ['nullable', 'email:rfc', 'max:180'],
            'website'     => ['nullable', 'url:https,http', 'max:255'],
            'address'     => ['nullable', 'string', 'max:255'],
            'logo'        => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp,gif', 'max:' . $maxKb],
            'cover'       => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp,gif', 'max:' . $maxKb],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_cover'=> ['nullable', 'boolean'],
            'accept'      => [$creating ? 'accepted' : 'nullable'],
            'domains'     => ['nullable', 'array'],
            'domains.*'   => [Rule::in(array_keys(\App\Support\SocialDomains::ALL))],
        ], [
            'name.required'   => 'Donnez un nom à votre page.',
            'accept.accepted' => 'Merci de confirmer que vous êtes autorisé(e) à représenter cette organisation.',
            'website.url'     => 'Adresse de site web invalide (commencez par https://).',
        ]);
    }

    private function saveImages(Request $request, Mutual $mutual): void
    {
        $profile = $mutual->profile()->first();
        foreach (['logo' => 512, 'cover' => 1600] as $field => $side) {
            $col = "{$field}_path";
            $old = $profile->{$col};
            if ($request->hasFile($field)) {
                $img = $this->media->storeImage($request->file($field), "social/mutuals/{$mutual->id}", $side);
                $this->media->delete($img['thumb_path']);
                $profile->{$col} = $img['path'];
            } elseif ($request->boolean("remove_{$field}")) {
                $profile->{$col} = null;
            }
            if ($old && $old !== $profile->{$col}) {
                $this->media->delete($old);
            }
        }
        $profile->save();
    }

    /** Pages CRÉÉES par ce membre (limite du nombre de pages). */
    private function ownedQuery()
    {
        return Mutual::query()->whereHas('profile', fn ($p) => $p->where('owner_account_id', $this->viewer()->id));
    }

    /** Page créée par un membre, que ce compte administre (créateur ou administrateur). */
    private function ownedPage(string $slug): Mutual
    {
        $mutual = Mutual::where('slug', $slug)->whereHas('profile', fn ($p) => $p->whereNotNull('owner_account_id'))->with('profile')->firstOrFail();
        abort_unless(\App\Services\Social\PageService::canAdminister($this->viewer(), $mutual), 404);

        return $mutual;
    }

    private function maxPages(): int
    {
        return (int) config('mxconnect.social.max_pages_per_account', 5);
    }

    private function countries()
    {
        return Country::where('active', true)->orderBy('name')->get(['id', 'name']);
    }

    private function newId(): string
    {
        do {
            $id = 'pg_' . Str::lower(Str::random(10));
        } while (Mutual::whereKey($id)->exists());

        return $id;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::limit(Str::slug($name), 50, '') ?: 'page';
        $slug = $base;
        for ($i = 2; Mutual::where('slug', $slug)->exists(); $i++) {
            $slug = Str::limit($base, 50 - strlen((string) $i) - 1, '') . '-' . $i;
        }

        return $slug;
    }
}
