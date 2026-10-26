<?php

namespace App\Services\Social;

use App\Models\Central\Mutual;
use App\Models\Central\PublicAccount;
use App\Models\Social\Follow;
use App\Models\Social\Mute;
use App\Models\Social\MutualProfile;
use App\Models\Social\Post;
use App\Models\Social\Reaction;
use App\Models\Social\SavedPost;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Fils d'actualité du réseau. Pagination par curseur (stable et rapide même avec
 * beaucoup de publications) + chargement groupé de l'état « j'aime / enregistré /
 * abonné » du visiteur pour éviter les requêtes en cascade.
 */
class FeedService
{
    public const WITH = ['mutual.profile', 'account', 'media', 'group', 'mentions.mutual', 'shared.mutual.profile', 'shared.account', 'shared.media', 'shared.group', 'shared.mentions.mutual', 'project', 'opportunity', 'documents', 'poll.options'];

    /** Accueil : toutes les publications visibles, sauf organisations masquées ; prioritaires en tête du jour. */
    public function home(?PublicAccount $viewer, array $filters = []): CursorPaginator
    {
        $q = $this->base($viewer, $filters);
        if ($viewer) {
            $q->where(fn ($w) => $w->whereNull('mutual_id')
                ->orWhereNotIn('mutual_id', Mute::where('public_account_id', $viewer->id)->select('mutual_id')));
        }

        return $this->paginate($q);
    }

    /** Toutes les publications publiques (aucun filtre personnel : découverte ouverte). */
    public function all(?PublicAccount $viewer, array $filters = []): CursorPaginator
    {
        return $this->paginate($this->base($viewer, $filters));
    }

    /** Uniquement les mutuelles suivies (et ses propres publications). */
    public function following(PublicAccount $viewer, array $filters = []): CursorPaginator
    {
        $q = $this->base($viewer, $filters)->where(function ($w) use ($viewer) {
            $w->whereIn('mutual_id', Follow::where('public_account_id', $viewer->id)->select('mutual_id'))
              ->orWhere('public_account_id', $viewer->id);
            // Membres suivis (abonnement entre comptes).
            if (\Illuminate\Support\Facades\Schema::connection('central')->hasTable('social_user_follows')) {
                $w->orWhere(fn ($m) => $m->whereNull('mutual_id')
                    ->whereIn('public_account_id', \App\Models\Social\UserFollow::where('follower_id', $viewer->id)->select('followed_id')));
            }
        });

        return $this->paginate($q);
    }

    public function forMutual(Mutual $mutual, ?PublicAccount $viewer, array $filters = []): CursorPaginator
    {
        return $this->paginate($this->base($viewer, $filters)->where('mutual_id', $mutual->id));
    }

    /** Réels : vidéos courtes verticales, les plus récentes d'abord (on peut ouvrir sur un réel précis). */
    public function reels(?PublicAccount $viewer): CursorPaginator
    {
        return $this->paginate($this->reelQuery($viewer));
    }

    public function reelQuery(?PublicAccount $viewer): Builder
    {
        return $this->base($viewer, [])->where('type', 'reel')->whereHas('media', fn ($m) => $m->where('kind', 'video'));
    }

    /** Fil d'un groupe (le contrôleur vérifie au préalable que le visiteur peut le lire). */
    public function forGroup(\App\Models\Social\Group $group, ?PublicAccount $viewer): CursorPaginator
    {
        $q = Post::query()->visibleTo($viewer)->with(self::WITH)->where('group_id', $group->id);

        return $this->paginate($q);
    }

    public function forAccount(PublicAccount $account, ?PublicAccount $viewer): CursorPaginator
    {
        return $this->paginate($this->base($viewer, [])->where('public_account_id', $account->id)->whereNull('mutual_id'));
    }

    public function saved(PublicAccount $viewer): CursorPaginator
    {
        return $this->paginate($this->base($viewer, [])
            ->whereIn('id', SavedPost::where('public_account_id', $viewer->id)->select('post_id')));
    }

    /** @param array{type?:string,category?:string,region?:string,city?:string,q?:string} $filters */
    protected function base(?PublicAccount $viewer, array $filters): Builder
    {
        $q = Post::query()->visibleTo($viewer)->with(self::WITH)
            ->whereNull('group_id')                 // fils généraux : hors publications de groupes
            ->where(fn ($w) => $w->whereNull('mutual_id')
                ->orWhereHas('mutual', fn ($m) => $m->where('status', '!=', 'suspended')));

        if (! empty($filters['type']) && isset(Post::TYPES[$filters['type']])) {
            $q->where('type', $filters['type']);
        }
        if (! empty($filters['category'])) {
            $q->where('category', $filters['category']);
        }
        if (! empty($filters['region'])) {
            $q->whereHas('mutual', fn ($m) => $m->where('region', $filters['region']));
        }
        if (! empty($filters['city'])) {
            $q->whereHas('mutual', fn ($m) => $m->where('city', $filters['city']));
        }
        if (! empty($filters['q'])) {
            $term = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $filters['q']) . '%';
            $q->where('body', 'like', $term);
        }

        return $q;
    }

    protected function paginate(Builder $q): CursorPaginator
    {
        return $q->orderByDesc('published_at')->orderByDesc('id')
            ->cursorPaginate(config('mxconnect.social.per_page', 12))->withQueryString();
    }

    /**
     * État du visiteur pour une page de publications : réactions, enregistrements,
     * abonnements. 3 requêtes au total, quel que soit le nombre de publications.
     *
     * @return array{reactions:array<int,string>,saved:array<int,bool>,follows:array<string,bool>}
     */
    public function viewerState(?PublicAccount $viewer, iterable $posts): array
    {
        $state = ['reactions' => [], 'saved' => [], 'follows' => [], 'votes' => []];
        if (! $viewer) {
            return $state;
        }
        // Inclut les publications d'origine des partages (affichées à la place du partage).
        $ids = collect($posts)->flatMap(fn ($p) => [$p->id, $p->shared_post_id])->filter()->unique()->values()->all();
        if ($ids) {
            $state['reactions'] = Reaction::where('public_account_id', $viewer->id)->whereIn('post_id', $ids)->pluck('type', 'post_id')->all();
            $state['saved'] = SavedPost::where('public_account_id', $viewer->id)->whereIn('post_id', $ids)->pluck('post_id')->flip()->map(fn () => true)->all();
            // Choix de ce compte dans les sondages affichés (pour montrer « votre vote »).
            $state['votes'] = \App\Models\Social\PollVote::query()->join('social_polls', 'social_polls.id', '=', 'social_poll_votes.poll_id')
                ->where('social_poll_votes.public_account_id', $viewer->id)->whereIn('social_polls.post_id', $ids)
                ->pluck('social_poll_votes.option_id', 'social_polls.post_id')->all();
        }
        $state['follows'] = Follow::where('public_account_id', $viewer->id)->pluck('mutual_id')->flip()->map(fn () => true)->all();

        return $state;
    }

    /**
     * « Tendances de la semaine » : mots-clés # les plus utilisés dans les publications
     * publiques des 7 derniers jours (comptage réel, démos exclues), mis en cache 10 min.
     * @return array<string,int>  mot-clé => nombre de publications
     */
    public function trends(int $limit = 5): array
    {
        return Cache::remember('social:trends:' . $limit, 600, function () use ($limit) {
            $counts = [];
            Post::query()->publicFeed()->where('is_demo', false)->where('published_at', '>=', now()->subDays(7))
                ->where('body', 'like', '%#%')->latest('published_at')->limit(500)->pluck('body')
                ->each(function ($body) use (&$counts) {
                    preg_match_all('/(?<![\p{L}\p{N}_&])#([\p{L}\p{N}_]{2,50})/u', (string) $body, $m);
                    foreach (array_unique(array_map('mb_strtolower', $m[1])) as $tag) {
                        $counts[$tag] = ($counts[$tag] ?? 0) + 1;
                    }
                });
            arsort($counts);

            return array_slice($counts, 0, $limit, true);
        });
    }

    /** Mutuelles visibles dans le réseau (tout sauf suspendues). */
    public function mutualsQuery(): Builder
    {
        return Mutual::query()->where('status', '!=', 'suspended')->with(['profile', 'country']);
    }

    /** Suggestions « à découvrir » (non suivies, triées par nombre d'abonnés). */
    public function suggestions(?PublicAccount $viewer, int $limit = 5): Collection
    {
        // Mise en cache courte : ce bloc s'affiche sur presque toutes les pages (charge réduite).
        return Cache::remember('social:sugg:' . ($viewer?->id ?? 0) . ':' . $limit, \App\Support\SocialLoad::light() ? 600 : 120,
            fn () => $this->suggestionsQuery($viewer, $limit));
    }

    private function suggestionsQuery(?PublicAccount $viewer, int $limit): Collection
    {
        $q = $this->mutualsQuery()
            ->leftJoin('mutual_profiles', 'mutual_profiles.mutual_id', '=', 'mutuals.id')
            ->select('mutuals.*')
            ->orderByDesc('mutual_profiles.followers_count')->orderBy('mutuals.name');
        if ($viewer) {
            $q->whereNotIn('mutuals.id', Follow::where('public_account_id', $viewer->id)->select('mutual_id'));
        }

        return $q->limit($limit)->get();
    }

    /**
     * Bandeau horizontal du fil : organisations suivies d'abord, puis les plus
     * suivies. recent_posts = nombre RÉEL de publications publiques des 7 derniers jours.
     */
    public function strip(?PublicAccount $viewer, int $limit = 10): Collection
    {
        $followed = $viewer ? Follow::where('public_account_id', $viewer->id)->pluck('mutual_id')->all() : [];
        $q = $this->mutualsQuery()
            ->leftJoin('mutual_profiles', 'mutual_profiles.mutual_id', '=', 'mutuals.id')
            ->select('mutuals.*');
        if ($followed) {
            $q->orderByRaw('CASE WHEN mutuals.id IN (' . implode(',', array_fill(0, count($followed), '?')) . ') THEN 0 ELSE 1 END', $followed);
        }
        $orgs = $q->orderByDesc('mutual_profiles.posts_count')->orderByDesc('mutual_profiles.followers_count')->orderBy('mutuals.name')
            ->limit($limit)->get();
        $recent = Post::query()->publicFeed()->whereIn('mutual_id', $orgs->pluck('id'))
            ->where('published_at', '>=', now()->subDays(7))->reorder()
            ->selectRaw('mutual_id, count(*) as n')->groupBy('mutual_id')->pluck('n', 'mutual_id');

        return $orgs->each(function ($m) use ($recent, $followed) {
            $m->recent_posts = (int) ($recent[$m->id] ?? 0);
            $m->is_followed = in_array($m->id, $followed, true);
        });
    }

    public function upcomingEvents(int $limit = 4): Collection
    {
        return Cache::remember('social:events:' . $limit, 120, fn () => Post::query()->publicFeed()->where('type', 'evenement')
            ->where('event_starts_at', '>=', now()->startOfDay())
            ->with('mutual.profile')->orderBy('event_starts_at')->limit($limit)->get());
    }

    public function campaigns(int $limit = 3): Collection
    {
        return Cache::remember('social:campaigns:' . $limit, 120, fn () => Post::query()->publicFeed()->where('type', 'campagne')
            ->with('mutual.profile')->orderByDesc('published_at')->limit($limit)->get());
    }

    /** Régions / villes réellement présentes (filtres d'exploration). */
    public function places(): array
    {
        return Cache::remember('social:places', 600, fn () => [
            'regions' => Mutual::where('status', '!=', 'suspended')->whereNotNull('region')->distinct()->orderBy('region')->pluck('region')->all(),
            'cities'  => Mutual::where('status', '!=', 'suspended')->whereNotNull('city')->distinct()->orderBy('city')->pluck('city')->all(),
        ]);
    }

    /** Recalcule les compteurs dénormalisés d'une mutuelle. */
    public function refreshMutualCounters(string $mutualId): void
    {
        MutualProfile::firstOrCreate(['mutual_id' => $mutualId])->update([
            'followers_count' => Follow::where('mutual_id', $mutualId)->count(),
            'posts_count'     => Post::where('mutual_id', $mutualId)->whereNull('group_id')->where('status', 'published')->count(),
        ]);
    }
}
