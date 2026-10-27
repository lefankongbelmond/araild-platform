<?php

namespace App\Http\Controllers\Social;

use App\Models\Central\PublicAccount;
use App\Models\Social\Follow;
use App\Models\Social\Mute;
use App\Models\Social\Post;
use App\Services\Social\FeedService;
use App\Services\Social\MediaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Profil public d'un membre, paramètres du compte, abonnements. */
class AccountController extends SocialController
{
    /** Centres d'intérêt proposés (thèmes de santé publique, pas de données médicales). */
    public const INTERESTS = [
        'prevention'  => 'Prévention',
        'maternite'   => 'Santé de la mère et de l’enfant',
        'nutrition'   => 'Nutrition',
        'vaccination' => 'Vaccination',
        'hygiene'     => 'Hygiène et assainissement',
        'solidarite'  => 'Solidarité communautaire',
        'jeunesse'    => 'Santé des jeunes',
        'seniors'     => 'Santé des aînés',
        'protection'  => 'Protection sociale',
    ];

    public function __construct(FeedService $feed, protected MediaService $media)
    {
        parent::__construct($feed);
    }

    public function show(Request $request, PublicAccount $account)
    {
        $viewer = $this->viewer();
        $isSelf = $viewer && $viewer->id === $account->id;
        $private = $account->profile_visibility === 'private' && ! $isSelf;

        abort_if($account->isSuspended() && ! $isSelf, 404);
        $data = [
            'account' => $account, 'isSelf' => $isSelf, 'private' => $private,
            'isFollowing' => $viewer && ! $isSelf && \App\Models\Social\UserFollow::where('follower_id', $viewer->id)->where('followed_id', $account->id)->exists(),
            'followersCount' => \App\Models\Social\UserFollow::where('followed_id', $account->id)->count(),
            'followingCount' => \App\Models\Social\UserFollow::where('follower_id', $account->id)->count(),
            'followed' => $private ? collect() : $account->follows()->with('mutual.profile')->latest()->limit(12)->get()->pluck('mutual')->filter(),
            'postsCount' => $private ? 0 : Post::visibleTo($viewer)->where('public_account_id', $account->id)->whereNull('mutual_id')->whereNull('group_id')->count(),
            'interests' => self::INTERESTS,
        ];

        if ($private) {
            return view('social.user', $data + ['posts' => null, 'state' => $this->feed->viewerState($viewer, []), 'followMap' => []]);
        }

        return $this->feedView($request, 'social.user', $this->feed->forAccount($account, $viewer), $data);
    }

    public function settings()
    {
        return view('social.settings', ['account' => $this->viewer(), 'interests' => self::INTERESTS]);
    }

    public function update(Request $request)
    {
        $account = $this->viewer();
        if ($request->filled('email')) {
            $request->merge(['email' => Str::lower(trim($request->input('email')))]);
        }
        $data = $request->validate([
            'first_name'         => ['required', 'string', 'max:120'],
            'last_name'          => ['required', 'string', 'max:120'],
            // Compte sans téléphone (inscription par e-mail, 2026-11-09) : l'e-mail est son seul identifiant.
            'email'              => [$account->phone ? 'nullable' : 'required', 'email:rfc', 'max:180', Rule::unique('central.public_accounts', 'email')->ignore($account->id)],
            'city'               => ['nullable', 'string', 'max:120'],
            'bio'                => ['nullable', 'string', 'max:280'],
            'interests'          => ['nullable', 'array'],
            'interests.*'        => ['in:' . implode(',', array_keys(self::INTERESTS))],
            'profile_visibility' => ['required', 'in:public,private'],
            'email_notifications' => ['nullable', 'boolean'],
            'avatar'             => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp,gif', 'max:' . config('mxconnect.social.max_upload_kb', 6144)],
            'remove_avatar'      => ['nullable', 'boolean'],
            'cover'              => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp,gif', 'max:' . config('mxconnect.social.max_upload_kb', 6144)],
            'remove_cover'       => ['nullable', 'boolean'],
        ]);

        $update = collect($data)->only(['first_name', 'last_name', 'email', 'city', 'bio', 'profile_visibility'])->all();
        $update['interests'] = array_values($data['interests'] ?? []);
        $update['email_notifications'] = $request->boolean('email_notifications');

        $old = $account->avatar_path;
        if ($request->hasFile('avatar')) {
            $img = $this->media->storeImage($request->file('avatar'), 'social/avatars', 512);
            $update['avatar_path'] = $img['path'];
            $this->media->delete($img['thumb_path']);
        } elseif ($request->boolean('remove_avatar')) {
            $update['avatar_path'] = null;
        }
        // Photo de couverture du profil (format paysage, convertie en WebP).
        $oldCover = $account->cover_path;
        if ($request->hasFile('cover')) {
            $img = $this->media->storeImage($request->file('cover'), 'social/covers', 1600);
            $update['cover_path'] = $img['path'];
            $this->media->delete($img['thumb_path']);
        } elseif ($request->boolean('remove_cover')) {
            $update['cover_path'] = null;
        }
        $account->update($update);
        if ($old && $old !== $account->avatar_path) {
            $this->media->delete($old);
        }
        if ($oldCover && $oldCover !== $account->cover_path) {
            $this->media->delete($oldCover);
        }

        return redirect()->route('social.settings')->with('ok', 'Profil mis à jour.');
    }

    public function password(Request $request)
    {
        $account = $this->viewer();
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);
        if (! Hash::check($data['current_password'], $account->password)) {
            return back()->withErrors(['current_password' => 'Mot de passe actuel incorrect.']);
        }
        $account->update(['password' => $data['password']]);
        $request->session()->regenerate();

        return redirect()->route('social.settings')->with('ok', 'Mot de passe modifié.');
    }

    /**
     * Suppression définitive du compte par son titulaire (mot de passe + confirmation).
     * Supprime publications, commentaires, réactions, abonnements, messages, demandes
     * d'adhésion et leurs pièces privées, photos. Les compteurs touchés sont recalculés.
     * Les fiches adhérent déjà créées par une mutuelle dans sa propre base ne sont pas
     * supprimées (elles relèvent de la gestion de la mutuelle) : seul le lien est retiré.
     */
    public function destroy(Request $request)
    {
        $account = $this->viewer();
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'confirm'          => ['required', 'in:SUPPRIMER'],
        ], ['confirm.in' => 'Tapez SUPPRIMER en majuscules pour confirmer.']);
        if (! Hash::check($data['current_password'], $account->password)) {
            return back()->withErrors(['current_password' => 'Mot de passe incorrect.']);
        }

        $id = $account->id;
        $postIds = \App\Models\Social\Post::withTrashed()->where('public_account_id', $id)->pluck('id');
        $touched = \App\Models\Social\Reaction::where('public_account_id', $id)->pluck('post_id')
            ->merge(\App\Models\Social\Comment::withTrashed()->where('public_account_id', $id)->pluck('post_id'))
            ->unique()->diff($postIds)->values();
        $mutualIds = Follow::where('public_account_id', $id)->pluck('mutual_id');
        $groupIds = \App\Models\Social\GroupMember::where('public_account_id', $id)->pluck('group_id');
        $files = \App\Models\Social\PostMedia::whereIn('post_id', $postIds)->get()->flatMap(fn ($m) => [$m->path, $m->thumb_path])
            ->push($account->avatar_path)->filter()->all();
        $dossiers = \App\Models\Social\AdhesionRequest::where('public_account_id', $id)->get(['mutual_id', 'reference']);

        // Suppression d'abord (cascade en base sur toutes les données sociales du compte),
        // déconnexion ensuite. Les jetons de l'espace adhérent (stockés dans la base de
        // chaque mutuelle) deviennent inopérants : le compte et ses liens n'existent plus.
        $account->delete();
        auth('member')->logout();

        $this->media->delete(...$files);
        foreach ($dossiers as $d) {
            \Illuminate\Support\Facades\Storage::disk('social_private')->deleteDirectory("adhesions/{$d->mutual_id}/{$d->reference}");
        }
        foreach (\App\Models\Social\Post::whereIn('id', $touched)->get() as $p) {
            $p->forceFill([
                'reactions_count' => \App\Models\Social\Reaction::where('post_id', $p->id)->count(),
                'comments_count'  => \App\Models\Social\Comment::where('post_id', $p->id)->where('status', 'visible')->count(),
            ])->save();
        }
        $mutualIds->each(fn ($m) => $this->feed->refreshMutualCounters($m));
        \App\Models\Social\Group::whereIn('id', $groupIds)->get()->each->refreshCounters();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('ok', 'Votre compte et vos données du réseau social ont été supprimés.');
    }

    /** Mutuelles suivies et masquées. */
    /** Suivre / ne plus suivre le compte d'un autre membre (profil public uniquement). */
    public function follow(Request $request, PublicAccount $account)
    {
        $viewer = $this->viewer();
        abort_if($account->id === $viewer->id, 422, 'Vous ne pouvez pas vous suivre vous-même.');
        abort_if($account->isSuspended(), 404);
        $existing = \App\Models\Social\UserFollow::where('follower_id', $viewer->id)->where('followed_id', $account->id)->first();
        if ($existing) {
            $existing->delete();
            $following = false;
        } else {
            abort_if($account->profile_visibility === 'private', 403, 'Ce profil est privé : il ne peut pas être suivi.');
            abort_if(\App\Models\Social\UserFollow::where('follower_id', $viewer->id)->count() >= 5000, 422, 'Nombre maximal d’abonnements atteint.');
            \App\Models\Social\UserFollow::create(['follower_id' => $viewer->id, 'followed_id' => $account->id]);
            $following = true;
            app(\App\Services\Social\NotificationService::class)->notify($account->id, 'follow', "{$viewer->name} s’est abonné(e) à vos publications", null, route('social.user', $viewer));
        }
        $followers = \App\Models\Social\UserFollow::where('followed_id', $account->id)->count();

        if ($request->expectsJson()) {
            return response()->json(['following' => $following, 'followers' => $followers, 'name' => $account->name]);
        }

        return back()->with('ok', $following ? "Vous suivez maintenant {$account->name}." : "Vous ne suivez plus {$account->name}.");
    }

    /** Signaler un profil. */
    public function report(Request $request, PublicAccount $account)
    {
        abort_if($account->id === $this->viewer()->id, 422, 'Vous ne pouvez pas signaler votre propre profil.');
        $data = $request->validate([
            'reason'  => ['required', 'in:' . implode(',', array_keys(\App\Models\Social\Report::REASONS))],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);
        \App\Models\Social\Report::firstOrCreate(
            ['public_account_id' => $this->viewer()->id, 'reported_account_id' => $account->id, 'status' => 'open'],
            ['reason' => $data['reason'], 'details' => $data['details'] ?? null],
        );

        return $request->expectsJson() ? response()->json(['ok' => true]) : back()->with('ok', 'Merci, votre signalement a été transmis.');
    }

    public function connections()
    {
        $viewer = $this->viewer();
        $follows = Follow::where('public_account_id', $viewer->id)->with('mutual.profile', 'mutual.country')->latest()->get()
            ->filter(fn ($f) => $f->mutual && $f->mutual->status !== 'suspended');
        $mutes = Mute::where('public_account_id', $viewer->id)->with('mutual.profile')->latest()->get()->filter(fn ($m) => $m->mutual);
        $followMap = $follows->pluck('mutual_id')->flip()->map(fn () => true)->all();

        $people = \App\Models\Social\UserFollow::where('follower_id', $viewer->id)->with('followed')->latest()->get()
            ->pluck('followed')->filter(fn ($a) => $a && ! $a->isSuspended());
        $followers = \App\Models\Social\UserFollow::where('followed_id', $viewer->id)->with('follower')->latest()->limit(100)->get()
            ->pluck('follower')->filter(fn ($a) => $a && ! $a->isSuspended());

        return view('social.connections', compact('follows', 'mutes', 'followMap', 'people', 'followers'));
    }
}
