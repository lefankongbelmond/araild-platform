<?php

namespace App\Services\Social;

use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Enums\PostVisibility;
use App\Models\Central\Hashtag;
use App\Models\Central\Organization;
use App\Models\Central\Post;
use App\Models\Central\PostComment;
use App\Models\Central\PostLike;
use App\Models\Central\Project;
use App\Models\Central\PublicAccount;
use App\Support\Hashtags;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Public feed: publishing, likes, comments, removal. Every rule is enforced here,
 * server-side — the UI only mirrors it.
 */
class PostService
{
    /** Calls to action and results belong to organizations (their requests land in an org inbox). */
    private const ORGANIZATION_ONLY_TYPES = [PostType::Event, PostType::Opportunity, PostType::Result];

    /**
     * @param  array{as?: string|int, type?: string, body?: ?string, visibility?: string, project_id?: ?int,
     *               event_starts_at?: ?string, event_location?: ?string, event_online?: bool,
     *               opportunity_kind?: ?string}  $data
     * @param  list<UploadedFile>  $files
     */
    public function publish(PublicAccount $actor, array $data, array $files = []): Post
    {
        $org = $this->resolveAuthorOrganization($actor, $data['as'] ?? 'me');
        $type = PostType::tryFrom($data['type'] ?? 'text') ?? PostType::Text;
        $body = trim((string) ($data['body'] ?? ''));

        if (! $org && in_array($type, self::ORGANIZATION_ONLY_TYPES, true)) {
            throw ValidationException::withMessages(['type' => __('mxconnect.social.errors.type_org_only')]);
        }
        if ($body === '' && $files === []) {
            throw ValidationException::withMessages(['body' => __('mxconnect.social.errors.empty_post')]);
        }

        $project = $this->resolveProject($org, $data['project_id'] ?? null);

        if ($type === PostType::Event && empty($data['event_starts_at'])) {
            throw ValidationException::withMessages(['event_starts_at' => __('mxconnect.social.errors.event_date_required')]);
        }
        if ($type === PostType::Opportunity && ! in_array($data['opportunity_kind'] ?? null, config('mxconnect.social.opportunity_kinds'), true)) {
            throw ValidationException::withMessages(['opportunity_kind' => __('mxconnect.social.errors.opportunity_kind_required')]);
        }

        return DB::connection('central')->transaction(function () use ($actor, $org, $type, $body, $data, $files, $project) {
            $media = array_map(fn (UploadedFile $f) => $this->storeMedia($f), $files);

            if ($type === PostType::Text && $media !== []) {
                $type = collect($media)->contains('type', 'video') ? PostType::Video : PostType::Photo;
            }

            $visibility = $org
                ? (PostVisibility::tryFrom($data['visibility'] ?? 'public') ?? PostVisibility::Public)
                : PostVisibility::Public; // individuals have no followers list

            $post = Post::create([
                'organization_id' => $org?->getKey(),
                'public_account_id' => $org ? null : $actor->getKey(),
                'published_by_account_id' => $actor->getKey(),
                'project_id' => $project?->getKey(),
                'type' => $type,
                'body' => $body === '' ? null : $body,
                'media' => $media ?: null,
                'event_starts_at' => $type === PostType::Event ? $data['event_starts_at'] : null,
                'event_location' => $type === PostType::Event ? ($data['event_location'] ?? null) : null,
                'event_online' => $type === PostType::Event && ! empty($data['event_online']),
                'opportunity_kind' => $type === PostType::Opportunity ? $data['opportunity_kind'] : null,
                'visibility' => $visibility,
                'status' => PostStatus::Published,
                'published_at' => now(),
                'likes_count' => 0,
                'comments_count' => 0,
                'shares_count' => 0,
            ]);

            $this->attachHashtags($post, Hashtags::extract($body));

            return $post->refresh();
        });
    }

    /** Author, or an owner/admin of the authoring organization, may remove a post. */
    public function remove(Post $post, PublicAccount $actor): void
    {
        $isAuthor = (int) $post->public_account_id === (int) $actor->getKey();
        $isOrgAdmin = $post->organization_id && $post->organization->isAdministeredBy($actor);

        if (! $isAuthor && ! $isOrgAdmin) {
            throw new AuthorizationException(__('mxconnect.social.errors.not_allowed'));
        }

        DB::connection('central')->transaction(function () use ($post) {
            $post->update(['status' => PostStatus::Removed]);
            $post->delete();
        });
    }

    /** @return bool the like state after the toggle */
    public function toggleLike(Post $post, PublicAccount $actor): bool
    {
        $this->assertVisible($post, $actor);

        return DB::connection('central')->transaction(function () use ($post, $actor) {
            $removed = PostLike::where('post_id', $post->getKey())
                ->where('public_account_id', $actor->getKey())
                ->delete();

            if ($removed > 0) {
                Post::whereKey($post->getKey())->where('likes_count', '>', 0)->decrement('likes_count');

                return false;
            }

            PostLike::create(['post_id' => $post->getKey(), 'public_account_id' => $actor->getKey(), 'reaction' => 'like']);
            Post::whereKey($post->getKey())->increment('likes_count');

            return true;
        });
    }

    public function comment(Post $post, PublicAccount $actor, string $body, ?int $parentId = null): PostComment
    {
        $this->assertVisible($post, $actor);

        $body = trim($body);
        if ($body === '') {
            throw ValidationException::withMessages(['body' => __('mxconnect.social.errors.empty_comment')]);
        }

        if ($parentId !== null) {
            $parent = PostComment::where('post_id', $post->getKey())->whereNull('parent_id')->find($parentId);
            if (! $parent) {
                throw ValidationException::withMessages(['parent_id' => __('mxconnect.social.errors.bad_parent')]);
            }
        }

        return DB::connection('central')->transaction(function () use ($post, $actor, $body, $parentId) {
            $comment = PostComment::create([
                'post_id' => $post->getKey(),
                'public_account_id' => $actor->getKey(),
                'parent_id' => $parentId,
                'body' => $body,
                'status' => 'active',
            ]);
            Post::whereKey($post->getKey())->increment('comments_count');

            return $comment->refresh();
        });
    }

    public function removeComment(PostComment $comment, PublicAccount $actor): void
    {
        $post = $comment->post;
        $isAuthor = (int) $comment->public_account_id === (int) $actor->getKey();
        $isOrgAdmin = $post->organization_id && $post->organization->isAdministeredBy($actor);

        if (! $isAuthor && ! $isOrgAdmin) {
            throw new AuthorizationException(__('mxconnect.social.errors.not_allowed'));
        }

        DB::connection('central')->transaction(function () use ($comment, $post) {
            $comment->update(['status' => 'removed']);
            $comment->delete();
            Post::whereKey($post->getKey())->where('comments_count', '>', 0)->decrement('comments_count');
        });
    }

    public function assertVisible(Post $post, ?PublicAccount $viewer): void
    {
        if (! $post->isVisibleTo($viewer)) {
            throw (new ModelNotFoundException)->setModel(Post::class, [$post->getKey()]);
        }
    }

    private function resolveAuthorOrganization(PublicAccount $actor, string|int $as): ?Organization
    {
        if ($as === 'me' || $as === '' || $as === null) {
            return null;
        }

        $org = Organization::find($as);
        if (! $org || ! $org->canPublishAs($actor)) {
            throw new AuthorizationException(__('mxconnect.social.errors.not_manager'));
        }
        if (! $org->isPublished()) {
            throw ValidationException::withMessages(['as' => __('mxconnect.social.errors.org_unavailable')]);
        }

        return $org;
    }

    private function resolveProject(?Organization $org, mixed $projectId): ?Project
    {
        if (blank($projectId)) {
            return null;
        }

        $project = $org ? Project::where('organization_id', $org->getKey())->find($projectId) : null;
        if (! $project) {
            throw ValidationException::withMessages(['project_id' => __('mxconnect.social.errors.bad_project')]);
        }

        return $project;
    }

    /** @return array{path: string, type: string} */
    private function storeMedia(UploadedFile $file): array
    {
        $type = str_starts_with((string) $file->getMimeType(), 'video/') ? 'video' : 'image';

        return ['path' => $file->store('posts/'.now()->format('Y/m'), 'public'), 'type' => $type];
    }

    /** @param list<string> $tags */
    private function attachHashtags(Post $post, array $tags): void
    {
        foreach ($tags as $tag) {
            $hashtag = Hashtag::firstOrCreate(['tag' => $tag], ['uses_count' => 0]);
            $hashtag->increment('uses_count');
            $post->hashtags()->attach($hashtag->getKey());
        }
    }
}
