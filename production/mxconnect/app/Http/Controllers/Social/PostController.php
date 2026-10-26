<?php

namespace App\Http\Controllers\Social;

use App\Models\Social\Comment;
use App\Models\Social\Post;
use App\Models\Social\Report;
use App\Models\Social\SavedPost;
use App\Services\Social\FeedService;
use App\Services\Social\PostService;
use Illuminate\Http\Request;

/**
 * Publications des comptes grand public et interactions. Chaque action vérifie
 * côté serveur que la publication est visible pour ce compte et qu'il en a le droit.
 */
class PostController extends SocialController
{
    public function __construct(FeedService $feed, protected PostService $posts)
    {
        parent::__construct($feed);
    }

    public function show(int $post)
    {
        $post = $this->visiblePost($post);
        $repostOf = null;
        // Partage sans commentaire : la page montre la publication d'origine (et ses commentaires).
        if ($post->shared_post_id && blank($post->body) && $post->media->isEmpty()) {
            $original = Post::query()->visibleTo($this->viewer())->with(FeedService::WITH)->find($post->shared_post_id);
            if ($original && $original->visibility === 'public') {
                [$repostOf, $post] = [$post, $original];
            }
        }
        $state = $this->feed->viewerState($this->viewer(), [$post]);

        return view('social.post', ['post' => $post, 'repostOf' => $repostOf, 'state' => $state, 'followMap' => $state['follows']]);
    }

    public function store(Request $request)
    {
        $maxKb = (int) config('mxconnect.social.max_upload_kb', 6144);
        $viewer = $this->viewer();
        $data = $request->validate([
            'body'       => ['nullable', 'string', 'max:5000'],
            'visibility' => ['nullable', 'in:public'],
            'type'       => ['nullable', 'in:' . implode(',', array_keys(Post::MEMBER_TYPES))],
            'images'     => ['nullable', 'array', 'max:' . config('mxconnect.social.max_images', 6)],
            'images.*'   => ['file', 'mimes:jpeg,jpg,png,webp,gif', 'max:' . $maxKb],
            'as'         => ['nullable', 'string', 'max:80'],
            'mentions'   => ['nullable', 'array', 'max:' . \App\Services\Social\MentionService::MAX],
            'mentions.*' => ['string', 'max:80'],
            'video_upload' => ['nullable', 'uuid'],
            'reel'       => ['nullable', 'boolean'],
            'poster'     => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp', 'max:3072'],
            'video_duration' => ['nullable', 'numeric', 'min:0', 'max:86400'],
            'video_width'    => ['nullable', 'integer', 'min:1', 'max:8000'],
            'video_height'   => ['nullable', 'integer', 'min:1', 'max:8000'],
        ], [
            'body.max'        => 'Le texte ne doit pas dépasser 5 000 caractères.',
            'type.in'         => 'Type de publication non disponible.',
            'images.max'      => 'Maximum :max images par publication.',
            'images.*.mimes'  => 'Seules les images JPEG, PNG, WebP ou GIF sont acceptées.',
            'images.*.max'    => 'Chaque image doit faire moins de ' . round($maxKb / 1024) . ' Mo.',
        ]);

        // « Publier en tant que » : soi-même ou l'une de SES pages (vérifié côté serveur).
        $page = null;
        if (! empty($data['as']) && $data['as'] !== 'me') {
            $page = \App\Services\Social\PageService::managedBy($viewer)->where('slug', $data['as'])->with('profile')->first();
            abort_unless($page, 403, 'Vous ne gérez pas cette page.');
        }

        $images = $request->file('images', []);
        $video = null;
        if (! empty($data['video_upload'])) {
            if ($images) {
                throw \Illuminate\Validation\ValidationException::withMessages(['video' => 'Publiez soit des photos, soit une vidéo.']);
            }
            $video = app(\App\Services\Social\VideoService::class)->finalize($data['video_upload'], $viewer, $request->file('poster'), [
                'duration' => $data['video_duration'] ?? null, 'width' => $data['video_width'] ?? null, 'height' => $data['video_height'] ?? null,
            ], (bool) ($data['reel'] ?? false));
        }
        $type = $video && ! empty($data['reel']) ? 'reel' : ($data['type'] ?? 'texte');

        try {
            $post = $this->posts->create(
                ['body' => $data['body'] ?? null, 'type' => $type, 'visibility' => 'public', 'video' => $video, 'mentions' => $data['mentions'] ?? []],
                $images, $viewer, $page,
            );
        } catch (\Throwable $e) {
            if ($video) {
                app(\App\Services\Social\MediaService::class)->delete($video['path'], $video['thumb_path']);
            }
            throw $e;
        }
        $url = $type === 'reel' ? route('social.reels', ['r' => $post->id])
            : ($page ? route('social.mutual', $page->slug) : route('social.home')) . '#post-' . $post->id;
        $msg = $type === 'reel' ? 'Votre réel est en ligne.' : 'Votre publication est en ligne.';

        // Envoi avec barre de progression (JavaScript) : réponse JSON, message affiché après rechargement.
        if ($request->expectsJson()) {
            session()->flash('ok', $msg);

            return response()->json(['ok' => true, 'url' => $url]);
        }

        return redirect()->to($url)->with('ok', $msg);
    }

    /** Début d'un envoi de vidéo par morceaux. */
    public function videoStart(Request $request, \App\Services\Social\VideoService $videos)
    {
        $data = $request->validate([
            'size' => ['required', 'integer', 'min:1'],
            'mime' => ['required', 'string', 'max:60'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);
        $upload = $videos->start($this->viewer(), (int) $data['size'], $data['mime'], $data['name'] ?? null);

        return response()->json(['id' => $upload->id, 'chunk' => \App\Services\Social\VideoService::chunkBytes()]);
    }

    /** Morceau suivant d'une vidéo. Renvoie le nombre d'octets reçus (reprise possible). */
    public function videoChunk(Request $request, string $upload, \App\Services\Social\VideoService $videos)
    {
        $data = $request->validate(['offset' => ['required', 'integer', 'min:0'], 'chunk' => ['required', 'file']]);
        $u = \App\Models\Social\Upload::where('id', $upload)->where('public_account_id', $this->viewer()->id)->firstOrFail();
        $received = $videos->append($u, (int) $data['offset'], $request->file('chunk'));

        return response()->json(['received' => $received, 'complete' => $received >= $u->size]);
    }

    /** Suggestions de mentions (@) : membres au profil public et pages. */
    public function mentionSearch(Request $request, \App\Services\Social\MentionService $mentions)
    {
        $q = $request->string('q')->trim()->limit(60, '')->toString();

        return response()->json(['results' => $mentions->search($q, $this->viewer())]);
    }

    public function destroy(int $post)
    {
        $post = Post::with('mutual.profile')->findOrFail($post);
        $viewer = $this->viewer();
        abort_unless($post->canManage($viewer), 403);
        $group = $post->group;
        $page = $post->mutual;
        $this->posts->delete($post);

        return match (true) {
            (bool) $group => redirect()->route('social.group', $group)->with('ok', 'Publication supprimée.'),
            (bool) $page  => redirect()->route('social.mutual', $page->slug)->with('ok', 'Publication supprimée.'),
            default       => redirect()->route('social.user', $viewer)->with('ok', 'Publication supprimée.'),
        };
    }

    public function react(Request $request, int $post)
    {
        $post = $this->visiblePost($post);
        $type = $request->string('type')->toString();
        [$mine, $total] = $this->posts->react($post, $this->viewer(), $type);

        return response()->json([
            'reaction' => $mine,
            'label'    => $mine ? Post::REACTIONS[$mine] : 'J’aime',
            'count'    => $total,
        ]);
    }

    public function save(int $post)
    {
        $post = $this->visiblePost($post);
        $viewer = $this->viewer();
        $existing = SavedPost::where('public_account_id', $viewer->id)->where('post_id', $post->id)->first();
        $existing ? $existing->delete() : SavedPost::create(['public_account_id' => $viewer->id, 'post_id' => $post->id]);

        return response()->json(['saved' => ! $existing]);
    }

    public function share(Request $request, int $post)
    {
        $post = $this->visiblePost($post);
        abort_unless($post->visibility === 'public' && (! $post->group_id || ! $post->group?->isPrivate()), 403,
            'Seules les publications publiques peuvent être partagées.');
        $data = $request->validate(['body' => ['nullable', 'string', 'max:1000']]);
        $shared = $this->posts->share($post, $this->viewer(), $data['body'] ?? null);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'url' => route('social.post', $shared)]);
        }

        return redirect()->route('social.post', $shared)->with('ok', 'Publication partagée.');
    }

    public function report(Request $request, int $post)
    {
        $post = $this->visiblePost($post);
        abort_if($post->public_account_id === $this->viewer()->id && ! $post->mutual_id, 422, 'Vous ne pouvez pas signaler votre propre publication.');

        return $this->storeReport($request, $post->id, null);
    }

    // --- Commentaires ---

    public function comment(Request $request, int $post)
    {
        $post = $this->visiblePost($post);
        $data = $request->validate([
            'body'       => ['required', 'string', 'max:2000'],
            'parent_id'  => ['nullable', 'integer'],
            'mentions'   => ['nullable', 'array', 'max:' . \App\Services\Social\MentionService::MAX],
            'mentions.*' => ['string', 'max:80'],
        ], ['body.required' => 'Le commentaire est vide.']);

        $comment = $this->posts->comment($post, $data['body'], $this->viewer(), null, $data['parent_id'] ?? null, null, $data['mentions'] ?? []);
        $comment->load(['account', 'mutual.profile', 'replies', 'mentions.mutual']);

        if ($request->expectsJson()) {
            return response()->json([
                'html'  => view('social.partials.comment', ['c' => $comment, 'post' => $post])->render(),
                'count' => $post->fresh()->comments_count,
            ]);
        }

        return redirect()->to(route('social.post', $post) . '#comment-' . $comment->id);
    }

    public function destroyComment(int $comment)
    {
        $comment = Comment::findOrFail($comment);
        abort_unless($comment->public_account_id === $this->viewer()->id, 403);
        $postId = $comment->post_id;
        $this->posts->deleteComment($comment);

        return redirect()->to(route('social.post', $postId) . '#commentaires')->with('ok', 'Commentaire supprimé.');
    }

    public function reportComment(Request $request, int $comment)
    {
        $comment = Comment::where('status', 'visible')->findOrFail($comment);
        $this->visiblePost($comment->post_id);
        abort_if($comment->public_account_id === $this->viewer()->id, 422, 'Vous ne pouvez pas signaler votre propre commentaire.');

        return $this->storeReport($request, null, $comment->id);
    }

    protected function storeReport(Request $request, ?int $postId, ?int $commentId)
    {
        $data = $request->validate([
            'reason'  => ['required', 'in:' . implode(',', array_keys(Report::REASONS))],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);
        Report::firstOrCreate(
            ['public_account_id' => $this->viewer()->id, 'post_id' => $postId, 'comment_id' => $commentId],
            ['reason' => $data['reason'], 'details' => $data['details'] ?? null],
        );

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('ok', 'Merci, votre signalement a été transmis.');
    }

    /** Publication visible pour le visiteur, sinon 404 (ne révèle pas son existence). */
    protected function visiblePost(int $id): Post
    {
        return Post::query()->visibleTo($this->viewer())->with(FeedService::WITH)
            ->where(fn ($w) => $w->whereNull('mutual_id')->orWhereHas('mutual', fn ($m) => $m->where('status', '!=', 'suspended')))
            ->findOrFail($id);
    }
}
