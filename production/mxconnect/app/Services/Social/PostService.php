<?php

namespace App\Services\Social;

use App\Models\Central\Mutual;
use App\Models\Central\PublicAccount;
use App\Models\Social\Comment;
use App\Models\Social\Post;
use App\Models\Social\PostMedia;
use App\Models\Social\Reaction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Écriture des publications et interactions. Les compteurs dénormalisés
 * (réactions, commentaires, partages) sont mis à jour dans la même transaction
 * que l'interaction pour rester cohérents avec les données enregistrées.
 */
class PostService
{
    public function __construct(private MediaService $media, private FeedService $feed, private NotificationService $notifications,
                                private MentionService $mentions, private LinkPreviewService $previews) {}

    /**
     * @param array{body?:string,type?:string,category?:string,link_url?:string,event_starts_at?:string,event_location?:string,visibility?:string,status?:string} $data
     * @param UploadedFile[] $images
     */
    public function create(array $data, array $images = [], ?PublicAccount $account = null, ?Mutual $mutual = null,
                           ?int $tenantUserId = null, ?string $authorLabel = null): Post
    {
        if (! $account && ! $mutual) {
            throw new \InvalidArgumentException('Une publication doit avoir un auteur.');
        }
        $max = config('mxconnect.social.max_images', 6);
        if (count($images) > $max) {
            throw ValidationException::withMessages(['images' => "Maximum {$max} images par publication."]);
        }
        $video = $data['video'] ?? null;      // vidéo déjà contrôlée et rangée par VideoService::finalize()
        if (trim((string) ($data['body'] ?? '')) === '' && ! $images && ! $video) {
            throw ValidationException::withMessages(['body' => 'Écrivez un message ou ajoutez une photo ou une vidéo.']);
        }

        // Aperçu du premier lien (seulement sans photo ni vidéo, comme sur Facebook).
        $preview = null;
        if (! $images && ! $video && empty($data['link_url'])) {
            $url = \App\Support\SocialText::firstUrl($data['body'] ?? null);
            $preview = $url ? ($this->previews->fetch($url) ?? ['link_url' => $url]) : null;
        }

        // Images traitées AVANT la transaction (fichiers), nettoyées si l'enregistrement échoue.
        $stored = [];
        try {
            foreach (array_values($images) as $file) {
                $stored[] = $this->media->storeImage($file, 'social/posts');
            }
        } catch (\Throwable $e) {
            foreach ($stored as $m) { $this->media->delete($m['path'], $m['thumb_path']); }
            throw $e;
        }

        $status = $data['status'] ?? 'published';
        try {
            $post = DB::connection('central')->transaction(function () use ($data, $stored, $video, $preview, $account, $mutual, $tenantUserId, $authorLabel, $status) {
                $post = Post::create([
                    'mutual_id'         => $mutual?->id,
                    'public_account_id' => $mutual ? null : $account?->id,
                    'group_id'          => $data['group_id'] ?? null,
                    'tenant_user_id'    => $tenantUserId,
                    'author_label'      => $authorLabel,
                    'type'              => $data['type'] ?? 'texte',
                    'category'          => $data['category'] ?? null,
                    'body'              => isset($data['body']) ? \App\Support\SocialText::normalize(trim($data['body'])) : null,   // texte collé nettoyé (2026-11-08)
                    'link_url'          => $data['link_url'] ?? ($preview['link_url'] ?? null),
                    'link_title'        => $preview['link_title'] ?? null,
                    'link_description'  => $preview['link_description'] ?? null,
                    'link_image_path'   => $preview['link_image_path'] ?? null,
                    'link_site'         => $preview['link_site'] ?? null,
                    'posted_by_account_id' => $mutual && $account ? $account->id : null,
                    'event_starts_at'   => $data['event_starts_at'] ?? null,
                    'event_location'    => $data['event_location'] ?? null,
                    'visibility'        => $data['visibility'] ?? 'public',
                    'status'            => $status,
                    'published_at'      => $status === 'published' ? now() : null,
                ]);
                foreach ($stored as $i => $m) {
                    PostMedia::create($m + ['post_id' => $post->id, 'position' => $i, 'kind' => 'image']);
                }
                if ($video) {
                    PostMedia::create($video + ['post_id' => $post->id, 'position' => 0]);
                }

                return $post;
            });
        } catch (\Throwable $e) {
            foreach ($stored as $m) { $this->media->delete($m['path'], $m['thumb_path']); }
            $this->media->delete($video['path'] ?? null, $video['thumb_path'] ?? null, $preview['link_image_path'] ?? null);
            throw $e;
        }

        if (! empty($data['mentions'])) {
            $this->mentions->attach($post, $post->body, (array) $data['mentions'], $mutual?->name ?? $account?->name ?? 'Quelqu’un', $account?->id);
        }

        if ($mutual && empty($data['group_id'])) {
            $this->feed->refreshMutualCounters($mutual->id);
        }
        $post->group?->refreshCounters();

        return $post->load(FeedService::WITH);
    }

    public function setStatus(Post $post, string $status): void
    {
        $post->update([
            'status'       => $status,
            'published_at' => $status === 'published' ? ($post->published_at ?? now()) : $post->published_at,
        ]);
        if ($post->mutual_id) {
            $this->feed->refreshMutualCounters($post->mutual_id);
        }
    }

    public function delete(Post $post): void
    {
        $paths = $post->media->flatMap(fn ($m) => [$m->path, $m->thumb_path])->push($post->link_image_path)
            ->merge($post->documents->pluck('path'))->all();
        $mutualId = $post->mutual_id;
        DB::connection('central')->transaction(function () use ($post) {
            if ($post->shared_post_id) {
                Post::whereKey($post->shared_post_id)->where('shares_count', '>', 0)->decrement('shares_count');
            }
            $post->media()->delete();
            $post->documents()->delete();
            $post->delete();
        });
        $this->media->delete(...$paths);
        $post->group?->refreshCounters();
        if ($mutualId) {
            $this->feed->refreshMutualCounters($mutualId);
        }
    }

    /** Ajoute, change ou retire la réaction du compte. Retourne [type|null, total]. */
    public function react(Post $post, PublicAccount $account, string $type): array
    {
        $type = array_key_exists($type, Post::REACTIONS) ? $type : 'jaime';

        return DB::connection('central')->transaction(function () use ($post, $account, $type) {
            $existing = Reaction::where('post_id', $post->id)->where('public_account_id', $account->id)->lockForUpdate()->first();
            if ($existing && $existing->type === $type) {
                $existing->delete();
                $mine = null;
            } elseif ($existing) {
                $existing->update(['type' => $type]);
                $mine = $type;
            } else {
                Reaction::create(['post_id' => $post->id, 'public_account_id' => $account->id, 'type' => $type]);
                $mine = $type;
            }
            $total = Reaction::where('post_id', $post->id)->count();
            $post->forceFill(['reactions_count' => $total])->save();

            return [$mine, $total];
        });
    }

    public function comment(Post $post, string $body, ?PublicAccount $account = null, ?Mutual $mutual = null,
                            ?int $parentId = null, ?string $label = null, array $mentions = []): Comment
    {
        if ($parentId && ! Comment::whereKey($parentId)->where('post_id', $post->id)->exists()) {
            throw ValidationException::withMessages(['body' => 'Commentaire parent introuvable.']);
        }

        $comment = DB::connection('central')->transaction(function () use ($post, $body, $account, $mutual, $parentId, $label) {
            $c = Comment::create([
                'post_id' => $post->id, 'parent_id' => $parentId, 'public_account_id' => $account?->id,
                'mutual_id' => $mutual?->id, 'author_label' => $label, 'body' => \App\Support\SocialText::normalize(trim($body)),
            ]);
            $post->forceFill(['comments_count' => Comment::where('post_id', $post->id)->where('status', 'visible')->count()])->save();

            return $c;
        });

        // Notifications : auteur de la publication, auteur du commentaire parent (jamais soi-même).
        $who = $account?->name ?? $mutual?->name ?? 'Quelqu’un';
        $url = '/publications/' . $post->id . '#comment-' . $comment->id;
        $notified = [];
        if ($parentId && ($parent = Comment::find($parentId)) && $parent->public_account_id
            && $parent->public_account_id !== $account?->id) {
            $this->notifications->notify($parent->public_account_id, 'reply', "{$who} a répondu à votre commentaire", \Illuminate\Support\Str::limit($body, 120), $url);
            $notified[] = $parent->public_account_id;
        }
        if ($post->public_account_id && ! $post->mutual_id && $post->public_account_id !== $account?->id
            && ! in_array($post->public_account_id, $notified, true)) {
            $this->notifications->notify($post->public_account_id, 'comment', "{$who} a commenté votre publication", \Illuminate\Support\Str::limit($body, 120), $url);
            $notified[] = $post->public_account_id;
        }
        // Publication d'une page créée par un membre : son propriétaire est prévenu.
        foreach ($post->mutual_id ? PageService::managerIds($post->mutual_id) : [] as $manager) {
            if ($manager !== $account?->id && ! in_array($manager, $notified, true)) {
                $this->notifications->notify($manager, 'comment', "{$who} a commenté une publication de votre page", \Illuminate\Support\Str::limit($body, 120), $url);
            }
        }
        if ($mentions) {
            $this->mentions->attach($comment, $comment->body, $mentions, $who, $account?->id);
        }

        return $comment;
    }

    public function deleteComment(Comment $comment): void
    {
        DB::connection('central')->transaction(function () use ($comment) {
            $post = $comment->post;
            $comment->delete();
            $post?->forceFill(['comments_count' => Comment::where('post_id', $post->id)->where('status', 'visible')->count()])->save();
        });
    }

    /** Partage : crée une publication du compte qui reprend l'originale. */
    public function share(Post $original, PublicAccount $account, ?string $body): Post
    {
        $root = $original->shared_post_id ? ($original->shared ?? $original) : $original;

        return DB::connection('central')->transaction(function () use ($root, $account, $body) {
            $post = Post::create([
                'public_account_id' => $account->id, 'type' => 'texte', 'body' => $body ? \App\Support\SocialText::normalize(trim($body)) : null,
                'shared_post_id' => $root->id, 'visibility' => 'public', 'status' => 'published', 'published_at' => now(),
            ]);
            $root->increment('shares_count');

            return $post;
        });
    }
}
