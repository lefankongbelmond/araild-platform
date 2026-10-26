<?php

namespace App\Http\Controllers\Social;

use App\Enums\PostType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Social\PostRequest;
use App\Models\Central\Hashtag;
use App\Models\Central\Organization;
use App\Models\Central\Post;
use App\Models\Central\PostComment;
use App\Models\Central\PostLike;
use App\Models\Central\PublicAccount;
use App\Services\Social\PostService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Module 4 — public feed. */
class FeedController extends Controller
{
    public const TABS = ['pour-vous', 'organisations', 'evenements', 'opportunites'];

    public function __construct(private PostService $posts) {}

    public function index(Request $request)
    {
        $viewer = $request->user('member');
        $tab = in_array($request->query('onglet'), self::TABS, true) ? $request->query('onglet') : 'pour-vous';
        $tag = $request->filled('tag') ? mb_strtolower((string) $request->query('tag')) : null;

        $query = Post::visibleTo($viewer)
            ->with(['organization', 'author', 'project', 'hashtags'])
            ->latest('published_at');

        match ($tab) {
            'organisations' => $query->whereNotNull('posts.organization_id'),
            'evenements' => $query->where('posts.type', PostType::Event->value),
            'opportunites' => $query->where('posts.type', PostType::Opportunity->value),
            default => $this->forYou($query, $viewer),
        };

        if ($tag) {
            $query->whereHas('hashtags', fn ($h) => $h->where('tag', $tag));
        }

        $posts = $query->paginate(config('mxconnect.social.feed_page_size'))->withQueryString();

        return view('social.feed.index', [
            'viewer' => $viewer,
            'tab' => $tab,
            'tag' => $tag,
            'posts' => $posts,
            'likedIds' => $this->likedIds($viewer, $posts->getCollection()->modelKeys()),
            'roles' => $viewer?->organizationRoles() ?? [],
            'authorOptions' => $this->authorOptions($viewer),
            'trending' => $this->trendingHashtags(),
            'suggestions' => $this->suggestions($viewer),
            'upcomingEvents' => Post::visibleTo($viewer)->with('organization')
                ->where('type', PostType::Event->value)->where('event_starts_at', '>=', now())
                ->orderBy('event_starts_at')->limit(3)->get(),
            'opportunities' => Post::visibleTo($viewer)->with('organization')
                ->where('type', PostType::Opportunity->value)->latest('published_at')->limit(3)->get(),
        ]);
    }

    public function show(Request $request, Post $post)
    {
        $viewer = $request->user('member');
        $this->posts->assertVisible($post, $viewer);

        $post->load(['organization', 'author', 'project', 'hashtags']);
        $comments = $post->comments()->whereNull('parent_id')
            ->with(['author', 'replies.author'])->oldest()->get();

        return view('social.feed.show', [
            'viewer' => $viewer,
            'post' => $post,
            'comments' => $comments,
            'likedIds' => $this->likedIds($viewer, [$post->getKey()]),
            'roles' => $viewer?->organizationRoles() ?? [],
        ]);
    }

    public function store(PostRequest $request)
    {
        $this->posts->publish($request->user('member'), $request->validated(), $request->file('media', []));

        return redirect()->route('feed')->with('success', __('mxconnect.social.feed.published'));
    }

    public function destroy(Request $request, Post $post)
    {
        $this->posts->remove($post, $request->user('member'));

        return redirect()->route('feed')->with('success', __('mxconnect.social.feed.removed'));
    }

    public function like(Request $request, Post $post)
    {
        $liked = $this->posts->toggleLike($post, $request->user('member'));

        if ($request->expectsJson()) {
            return response()->json(['liked' => $liked, 'likes_count' => $post->refresh()->likes_count]);
        }

        return back();
    }

    public function comment(Request $request, Post $post)
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'integer'],
        ]);

        $this->posts->comment($post, $request->user('member'), $data['body'], $data['parent_id'] ?? null);

        return redirect()->to(route('posts.show', $post).'#commentaires');
    }

    public function destroyComment(Request $request, PostComment $comment)
    {
        $post = $comment->post;
        $this->posts->removeComment($comment, $request->user('member'));

        return redirect()->to(route('posts.show', $post).'#commentaires');
    }

    /** "Pour vous": followed + managed organizations and own posts; everything public otherwise. */
    private function forYou($query, ?PublicAccount $viewer): void
    {
        if (! $viewer) {
            return;
        }

        $orgIds = $viewer->follows()->pluck('organizations.id')
            ->merge($viewer->managedOrganizations()->pluck('organizations.id'));

        if ($orgIds->isEmpty()) {
            return;
        }

        $query->where(fn ($q) => $q->whereIn('posts.organization_id', $orgIds)
            ->orWhere('posts.public_account_id', $viewer->getKey()));
    }

    private function likedIds(?PublicAccount $viewer, array $postIds): array
    {
        if (! $viewer || $postIds === []) {
            return [];
        }

        return PostLike::where('public_account_id', $viewer->getKey())
            ->whereIn('post_id', $postIds)
            ->pluck('post_id')->all();
    }

    /** Organizations the viewer may publish as, with their projects (for the project link). */
    private function authorOptions(?PublicAccount $viewer): Collection
    {
        if (! $viewer) {
            return collect();
        }

        return $viewer->managedOrganizations()->published()->with('projects:id,organization_id,title')->get();
    }

    /** Most-used hashtags on public posts of the last 7 days. */
    private function trendingHashtags(): Collection
    {
        return Hashtag::query()
            ->select('hashtags.tag', DB::raw('count(*) as recent_uses'))
            ->join('post_hashtag', 'post_hashtag.hashtag_id', '=', 'hashtags.id')
            ->join('posts', 'posts.id', '=', 'post_hashtag.post_id')
            ->where('posts.status', 'published')
            ->where('posts.visibility', 'public')
            ->whereNull('posts.deleted_at')
            ->where('posts.published_at', '>=', now()->subDays(7))
            ->groupBy('hashtags.tag')
            ->orderByDesc('recent_uses')
            ->limit(5)
            ->get();
    }

    private function suggestions(?PublicAccount $viewer): Collection
    {
        $exclude = $viewer
            ? $viewer->follows()->pluck('organizations.id')->merge($viewer->managedOrganizations()->pluck('organizations.id'))
            : collect();

        return Organization::published()->whereNotIn('id', $exclude)
            ->orderByDesc('followers_count')->latest()->limit(5)->get();
    }
}
