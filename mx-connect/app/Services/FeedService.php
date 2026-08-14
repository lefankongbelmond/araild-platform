<?php

namespace App\Services;

use App\Events\FeedPostCreated;
use App\Models\Tenant\FeedComment;
use App\Models\Tenant\FeedPost;
use App\Models\Tenant\FeedPostLike;
use App\Models\Tenant\Member;
use Illuminate\Support\Facades\DB;

/**
 * Member social feed (CDC Phase 5). Tenant-scoped: every method runs inside the
 * current tenant's database. Counters are maintained on the post row so the feed
 * lists without N+1 aggregation; a new post is broadcast live to the mutual.
 */
class FeedService
{
    /** Publish a post and broadcast it to the mutual's feed channel. */
    public function createPost(Member $member, string $body): FeedPost
    {
        $post = FeedPost::create([
            'member_id' => $member->id,
            'body'      => trim($body),
            'status'    => 'published',
        ]);

        $author = trim(($member->first_name ?? '') . ' ' . ($member->last_name ?? '')) ?: __('mxconnect.feed.member');

        FeedPostCreated::dispatch($post, (string) tenant('id'), $author);

        return $post;
    }

    /**
     * Toggle a member's like on a post. Returns the new state and count.
     * @return array{liked:bool,likes_count:int}
     */
    public function toggleLike(Member $member, FeedPost $post): array
    {
        return DB::transaction(function () use ($member, $post) {
            $existing = FeedPostLike::where('feed_post_id', $post->id)
                ->where('member_id', $member->id)->first();

            if ($existing) {
                $existing->delete();
                $post->decrement('likes_count');
                $liked = false;
            } else {
                FeedPostLike::create(['feed_post_id' => $post->id, 'member_id' => $member->id]);
                $post->increment('likes_count');
                $liked = true;
            }

            return ['liked' => $liked, 'likes_count' => (int) $post->fresh()->likes_count];
        });
    }

    /** Add a comment and keep the post's comment counter in sync. */
    public function addComment(Member $member, FeedPost $post, string $body): FeedComment
    {
        return DB::transaction(function () use ($member, $post, $body) {
            $comment = FeedComment::create([
                'feed_post_id' => $post->id,
                'member_id'    => $member->id,
                'body'         => trim($body),
            ]);
            $post->increment('comments_count');

            return $comment;
        });
    }

    /** Published posts, newest first, with author + like/comment counts. */
    public function feed(int $limit = 30, ?int $forMemberId = null): array
    {
        return FeedPost::where('status', 'published')
            ->with('member:id,first_name,last_name')
            ->latest()->limit($limit)->get()
            ->map(fn (FeedPost $p) => [
                'id'             => $p->id,
                'author'         => trim(($p->member->first_name ?? '') . ' ' . ($p->member->last_name ?? '')) ?: __('mxconnect.feed.member'),
                'body'           => $p->body,
                'likes_count'    => (int) $p->likes_count,
                'comments_count' => (int) $p->comments_count,
                'liked'          => $forMemberId ? $p->likedBy($forMemberId) : false,
                'created_at'     => $p->created_at?->toIso8601String(),
            ])->all();
    }
}
