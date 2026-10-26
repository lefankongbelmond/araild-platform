<?php

namespace App\Http\Controllers\Social;

use App\Models\Central\Mutual;
use App\Models\Central\PublicAccount;
use App\Models\Social\MutualProfile;
use App\Models\Social\Post;
use Illuminate\Http\Request;

/** Fils d'actualité, exploration, recherche, agenda, enregistrements. */
class FeedController extends SocialController
{
    /** Accueil : fil général (sans les organisations masquées). */
    public function home(Request $request)
    {
        return $this->feedView($request, 'social.feed', $this->feed->home($this->viewer(), $this->filters($request)), [
            'tab' => 'fil', 'title' => 'Accueil', 'composer' => true,
        ]);
    }

    /** Réels : vidéos courtes en plein écran, défilement vertical. */
    public function reels(Request $request)
    {
        $viewer = $this->viewer();
        $reels = $this->feed->reels($viewer);
        // Lien vers un réel précis (?r=) : il est montré en premier.
        $first = ! $request->has('cursor') && $request->integer('r')
            ? $this->feed->reelQuery($viewer)->find($request->integer('r')) : null;
        $state = $this->feed->viewerState($viewer, array_filter(array_merge([$first], $reels->items())));
        $data = ['reels' => $reels, 'first' => $first, 'state' => $state];
        if ($request->boolean('fragment')) {
            return response()->view('social.partials.reels', $data)->header('Cache-Control', 'no-store');
        }

        return view('social.reels', $data);
    }

    /** Toutes les publications publiques, filtrables. */
    public function all(Request $request)
    {
        return $this->feedView($request, 'social.feed', $this->feed->all($this->viewer(), $this->filters($request)), [
            'tab' => 'toutes', 'title' => 'Toutes les publications', 'filtersBar' => true, 'places' => $this->feed->places(),
        ]);
    }

    /** Uniquement les mutuelles suivies. */
    public function following(Request $request)
    {
        $viewer = $this->viewer();

        return $this->feedView($request, 'social.feed', $this->feed->following($viewer, $this->filters($request)), [
            'tab' => 'abonnements', 'title' => 'Mes abonnements',
            'followCount' => $viewer->follows()->count(),
        ]);
    }

    /** Découverte : mutuelles + publications les plus suivies récemment. */
    public function explore(Request $request)
    {
        $viewer = $this->viewer();
        $category = $request->string('categorie')->toString();
        $region = $request->string('region')->toString();

        $mutuals = $this->feed->mutualsQuery()
            ->when($region, fn ($q) => $q->where('region', $region))
            ->tap(fn ($q) => MutualProfile::applyCategory($q, $category))
            ->leftJoin('mutual_profiles', 'mutual_profiles.mutual_id', '=', 'mutuals.id')
            ->select('mutuals.*')
            ->orderByDesc('mutual_profiles.followers_count')->orderBy('mutuals.name')
            ->limit(12)->get();

        $popular = Post::query()->visibleTo($viewer)->whereNull('group_id')->with(\App\Services\Social\FeedService::WITH)
            ->where('published_at', '>=', now()->subDays(30))
            ->orderByRaw('(reactions_count + 2 * comments_count + 3 * shares_count) desc')
            ->orderByDesc('published_at')->limit(6)->get();

        $state = $this->feed->viewerState($viewer, $popular);

        return view('social.explore', [
            'mutuals' => $mutuals, 'popular' => $popular, 'state' => $state, 'followMap' => $state['follows'],
            'places' => $this->feed->places(), 'category' => $category, 'region' => $region,
        ]);
    }

    public function search(Request $request)
    {
        $q = $request->string('q')->trim()->limit(100, '')->toString();
        $tab = in_array($request->get('onglet'), ['mutuelles', 'publications', 'membres'], true) ? $request->get('onglet') : 'tout';
        $viewer = $this->viewer();
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';

        $mutuals = collect(); $members = collect(); $posts = null; $state = ['reactions' => [], 'saved' => [], 'follows' => []];
        if (mb_strlen($q) >= 2) {
            if (in_array($tab, ['tout', 'mutuelles'], true)) {
                $mutuals = $this->feed->mutualsQuery()
                    ->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('city', 'like', $like)->orWhere('region', 'like', $like)
                        ->orWhereHas('profile', fn ($p) => $p->where('tagline', 'like', $like)->orWhere('description', 'like', $like)))
                    ->orderBy('name')->limit($tab === 'tout' ? 5 : 30)->get();
            }
            if (in_array($tab, ['tout', 'membres'], true)) {
                $members = PublicAccount::query()->where('profile_visibility', 'public')->whereNull('suspended_at')
                    ->where(fn ($w) => $w->whereRaw("CONCAT(first_name, ' ', last_name) like ?", [$like])->orWhere('city', 'like', $like))
                    ->orderBy('first_name')->limit($tab === 'tout' ? 5 : 30)
                    ->get(['id', 'first_name', 'last_name', 'avatar_path', 'city', 'bio']);
            }
            if (in_array($tab, ['tout', 'publications'], true)) {
                $posts = $this->feed->all($viewer, ['q' => $q]);
                if ($request->boolean('fragment')) {
                    return $this->feedView($request, 'social.search', $posts);
                }
                $state = $this->feed->viewerState($viewer, $posts->items());
            }
        }
        $state['follows'] = $state['follows'] ?: $this->feed->viewerState($viewer, [])['follows'];

        return view('social.search', compact('q', 'tab', 'mutuals', 'members', 'posts', 'state') + ['followMap' => $state['follows']]);
    }

    public function events(Request $request)
    {
        $past = $request->get('periode') === 'passes';
        $viewer = $this->viewer();
        $q = Post::query()->visibleTo($viewer)->whereNull('group_id')->with(\App\Services\Social\FeedService::WITH)
            ->where('type', 'evenement')->whereNotNull('event_starts_at');
        $q = $past
            ? $q->where('event_starts_at', '<', now()->startOfDay())->orderByDesc('event_starts_at')->orderByDesc('id')
            : $q->where('event_starts_at', '>=', now()->startOfDay())->orderBy('event_starts_at')->orderBy('id');
        $posts = $q->cursorPaginate(config('mxconnect.social.per_page', 12))->withQueryString();

        return $this->feedView($request, 'social.events', $posts, ['past' => $past]);
    }

    public function saved(Request $request)
    {
        return $this->feedView($request, 'social.feed', $this->feed->saved($this->viewer()), [
            'tab' => 'enregistrements', 'title' => 'Enregistrements',
        ]);
    }
}
