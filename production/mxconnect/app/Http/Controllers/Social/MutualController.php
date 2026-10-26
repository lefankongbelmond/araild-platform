<?php

namespace App\Http\Controllers\Social;

use App\Models\Central\Mutual;
use App\Models\Central\MutualOffer;
use App\Models\Social\ContactRequest;
use App\Models\Social\Follow;
use App\Models\Social\Mute;
use App\Models\Social\MutualProfile;
use App\Models\Social\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Annuaire et pages publiques des mutuelles, abonnement (≠ adhésion), masquage,
 * prise de contact. Suivre une mutuelle ne crée AUCUN lien d'adhérent.
 */
class MutualController extends SocialController
{
    public const TABS = [
        'publications' => 'Publications',
        'a-propos'     => 'À propos',
        'activites'    => 'Activités',
        'evenements'   => 'Événements',
        'photos'       => 'Photos',
        'offres'       => 'Offres',
        'contact'      => 'Contact',
    ];

    public function index(Request $request)
    {
        $q = $request->string('q')->trim()->limit(100, '')->toString();
        $region = $request->string('region')->toString();
        $city = $request->string('ville')->toString();
        $category = $request->string('categorie')->toString();
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';

        $mutuals = $this->feed->mutualsQuery()
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', $like)->orWhere('city', 'like', $like)))
            ->when($region, fn ($w) => $w->where('region', $region))
            ->when($city, fn ($w) => $w->where('city', $city))
            ->tap(fn ($w) => MutualProfile::applyCategory($w, $category))
            ->orderBy('name')->paginate(18)->withQueryString();

        $state = $this->feed->viewerState($this->viewer(), []);

        return view('social.mutuals', [
            'mutuals' => $mutuals, 'followMap' => $state['follows'], 'places' => $this->feed->places(),
            'q' => $q, 'region' => $region, 'city' => $city, 'category' => $category,
        ]);
    }

    public function show(Request $request, string $slug)
    {
        $mutual = $this->findMutual($slug);
        $viewer = $this->viewer();
        $tab = array_key_exists($request->get('onglet'), self::TABS) ? $request->get('onglet') : 'publications';
        $profile = $mutual->profile ?? new MutualProfile(['mutual_id' => $mutual->id]);

        $following = $viewer && Follow::where('public_account_id', $viewer->id)->where('mutual_id', $mutual->id)->exists();
        $muted = $viewer && Mute::where('public_account_id', $viewer->id)->where('mutual_id', $mutual->id)->exists();

        $isPage = (bool) $profile->owner_account_id;
        if ($isPage && $tab === 'offres') {
            $tab = 'publications';
        }
        // Gestion de la page (propriétaire ou administrateur) et publication (tout gestionnaire, mutuelles comprises).
        $isOwner = $isPage && \App\Services\Social\PageService::canAdminister($viewer, $mutual);
        $canPublish = \App\Services\Social\PageService::canPublish($viewer, $mutual);
        $data = compact('mutual', 'profile', 'tab', 'following', 'muted', 'isPage', 'isOwner', 'canPublish') + [
            'tabs'   => $isPage ? array_diff_key(self::TABS, ['offres' => 1]) : self::TABS,
            'offer'  => MutualOffer::where('mutual_id', $mutual->id)->where('published', true)->with('currency')->first(),
            'stats'  => [
                'followers' => (int) ($profile->followers_count ?? 0),
                'posts'     => (int) ($profile->posts_count ?? 0),
                'events'    => Post::publicFeed()->where('mutual_id', $mutual->id)->where('type', 'evenement')->count(),
            ],
        ];

        if (in_array($tab, ['publications', 'activites', 'evenements'], true)) {
            $filters = match ($tab) {
                'activites'  => ['type' => 'activite'],
                'evenements' => ['type' => 'evenement'],
                default      => $this->filters($request),
            };
            $posts = $this->feed->forMutual($mutual, $viewer, $filters);

            return $this->feedView($request, 'social.mutual', $posts, $data);
        }

        if ($tab === 'photos') {
            // Album : photos des publications que ce visiteur a le droit de voir.
            $data['photos'] = \App\Models\Social\PostMedia::whereIn('post_id',
                Post::query()->visibleTo($viewer)->whereNull('group_id')->where('mutual_id', $mutual->id)->select('id'))
                ->orderByDesc('id')->paginate(24)->withQueryString();
        }
        $state = $this->feed->viewerState($viewer, []);

        return view('social.mutual', $data + ['posts' => null, 'state' => $state, 'followMap' => $state['follows']]);
    }

    /** Suivre / ne plus suivre (bascule). */
    public function follow(Request $request, string $slug)
    {
        $mutual = $this->findMutual($slug);
        $viewer = $this->viewer();

        $following = DB::connection('central')->transaction(function () use ($viewer, $mutual) {
            $existing = Follow::where('public_account_id', $viewer->id)->where('mutual_id', $mutual->id)->lockForUpdate()->first();
            if ($existing) {
                $existing->delete();

                return false;
            }
            Follow::create(['public_account_id' => $viewer->id, 'mutual_id' => $mutual->id]);
            // Suivre une mutuelle annule son masquage éventuel.
            Mute::where('public_account_id', $viewer->id)->where('mutual_id', $mutual->id)->delete();

            return true;
        });
        $this->feed->refreshMutualCounters($mutual->id);
        foreach ([4, 5] as $n) { cache()->forget("social:sugg:{$viewer->id}:{$n}"); }
        $followers = (int) MutualProfile::whereKey($mutual->id)->value('followers_count');
        // Page créée par un membre : son propriétaire est prévenu d'un nouvel abonné.
        if ($following) {
            foreach (\App\Services\Social\PageService::managerIds($mutual->id) as $manager) {
                if ($manager !== $viewer->id) {
                    app(\App\Services\Social\NotificationService::class)->notify($manager, 'follow', "{$viewer->name} suit maintenant votre page {$mutual->name}", null, route('social.mutual', $mutual->slug));
                }
            }
        }

        if ($request->expectsJson()) {
            return response()->json(['following' => $following, 'followers' => $followers, 'name' => $mutual->name]);
        }

        return back()->with('ok', $following ? "Vous suivez maintenant {$mutual->name}." : "Vous ne suivez plus {$mutual->name}.");
    }

    /** Masquer / réafficher une mutuelle dans « Accueil ». */
    public function mute(Request $request, string $slug)
    {
        $mutual = $this->findMutual($slug);
        $viewer = $this->viewer();
        $mute = Mute::where('public_account_id', $viewer->id)->where('mutual_id', $mutual->id)->first();
        if ($mute) {
            $mute->delete();
            $msg = "Les publications de {$mutual->name} réapparaîtront dans votre fil.";
        } else {
            Mute::create(['public_account_id' => $viewer->id, 'mutual_id' => $mutual->id]);
            $msg = "{$mutual->name} est masquée de votre fil d'accueil. Vous pouvez annuler depuis « Mes connexions ».";
        }

        if ($request->expectsJson()) {
            return response()->json(['muted' => ! $mute, 'message' => $msg]);
        }

        return back()->with('ok', $msg);
    }

    /** Message de contact vers la mutuelle (consulté dans son back-office). */
    public function contact(Request $request, string $slug)
    {
        $mutual = $this->findMutual($slug);
        $viewer = $this->viewer();
        abort_if($mutual->profile?->owner_account_id, 403, 'Utilisez les coordonnées indiquées sur la page.');

        $data = $request->validate([
            'name'    => [$viewer ? 'nullable' : 'required', 'string', 'max:180'],
            'email'   => ['nullable', 'email:rfc', 'max:180', 'required_without:phone'],
            'phone'   => ['nullable', 'string', 'max:40', 'required_without:email'],
            'subject' => ['required', 'in:' . implode(',', array_keys(ContactRequest::SUBJECTS))],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
            'consent' => ['accepted'],
        ], [
            'email.required_without' => 'Indiquez un e-mail ou un téléphone pour être recontacté.',
            'phone.required_without' => 'Indiquez un téléphone ou un e-mail pour être recontacté.',
            'consent.accepted'       => 'Merci d’accepter la transmission de votre message à la mutuelle.',
        ]);

        ContactRequest::create([
            'mutual_id'         => $mutual->id,
            'public_account_id' => $viewer?->id,
            'name'              => ($data['name'] ?? null) ?: $viewer?->name,
            'email'             => $data['email'] ?? null,
            'phone'             => $data['phone'] ?? null,
            'subject'           => $data['subject'],
            'message'           => $data['message'],
        ]);

        return redirect()->route('social.mutual', [$mutual->slug, 'onglet' => 'contact'])
            ->with('ok', "Votre message a été transmis à {$mutual->name}. La mutuelle vous répondra directement.");
    }

    /** Signaler une page ou une organisation. */
    public function report(Request $request, string $slug)
    {
        $mutual = $this->findMutual($slug);
        $data = $request->validate([
            'reason'  => ['required', 'in:' . implode(',', array_keys(\App\Models\Social\Report::REASONS))],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);
        \App\Models\Social\Report::firstOrCreate(
            ['public_account_id' => $this->viewer()->id, 'reported_mutual_id' => $mutual->id, 'status' => 'open'],
            ['reason' => $data['reason'], 'details' => $data['details'] ?? null],
        );

        return $request->expectsJson() ? response()->json(['ok' => true]) : back()->with('ok', 'Merci, votre signalement a été transmis.');
    }

    protected function findMutual(string $slug): Mutual
    {
        $mutual = Mutual::where('slug', $slug)->with(['profile', 'country', 'currency'])->firstOrFail();
        abort_if($mutual->status === 'suspended', 404);

        return $mutual;
    }
}
