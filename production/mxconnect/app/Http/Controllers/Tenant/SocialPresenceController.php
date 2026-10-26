<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Central\Mutual;
use App\Models\Social\Comment;
use App\Models\Social\ContactRequest;
use App\Models\Social\MutualProfile;
use App\Models\Social\Post;
use App\Models\Social\PostMedia;
use App\Services\Social\FeedService;
use App\Services\Social\MediaService;
use App\Services\Social\PostService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Back-office de la mutuelle : sa page publique sur le réseau social, ses
 * publications et les messages reçus. Réservé aux rôles mutual_admin et
 * community_manager (vérifié côté serveur à chaque action). La mutuelle ne
 * peut agir QUE sur ses propres contenus (mutual_id = mutuelle courante).
 */
class SocialPresenceController extends Controller
{
    public const ROLES = ['mutual_admin', 'community_manager'];
    /** Types proposés aux mutuelles (« document » : en attente du dépôt de fichiers). */
    public const POST_TYPES = ['annonce', 'campagne', 'activite', 'evenement', 'offre', 'texte', 'lien'];

    public function __construct(private PostService $posts, private MediaService $media, private FeedService $feed) {}

    public function index(Request $request)
    {
        $mutual = $this->authorizeMutual($request);
        $status = in_array($request->get('statut'), ['published', 'draft', 'archived', 'hidden'], true) ? $request->get('statut') : null;

        $posts = Post::where('mutual_id', $mutual->id)->with('media')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByRaw('published_at is null desc')->orderByDesc('published_at')->orderByDesc('id')
            ->paginate(20)->withQueryString();

        $since = now()->subDays(30);
        $base = Post::where('mutual_id', $mutual->id)->where('status', 'published');
        $stats = [
            'followers'   => \App\Models\Social\Follow::where('mutual_id', $mutual->id)->count(),
            'published'   => (clone $base)->count(),
            'reactions'   => (int) (clone $base)->sum('reactions_count'),
            'comments'    => (int) (clone $base)->sum('comments_count'),
            'shares'      => (int) (clone $base)->sum('shares_count'),
            'posts_30d'   => (clone $base)->where('published_at', '>=', $since)->count(),
            'new_contact' => ContactRequest::where('mutual_id', $mutual->id)->where('status', 'new')->count(),
        ];

        return view('tenant.social.index', [
            'mutual' => $mutual, 'profile' => $mutual->profile, 'posts' => $posts, 'stats' => $stats,
            'status' => $status, 'publicUrl' => $this->publicUrl('/mutuelles/' . $mutual->slug),
        ]);
    }

    // --- Page publique ---

    public function editProfile(Request $request)
    {
        $mutual = $this->authorizeMutual($request);

        return view('tenant.social.profile', [
            'managers' => \App\Models\Social\PageAdmin::where('mutual_id', $mutual->id)->with('account')->orderBy('created_at')->get(),
            'mutual' => $mutual,
            'profile' => $mutual->profile ?? new MutualProfile(['category' => 'mutuelle']),
            'central' => $this->publicUrl(''),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $mutual = $this->authorizeMutual($request);
        $maxKb = (int) config('mxconnect.social.max_upload_kb', 6144);
        $data = $request->validate([
            'category'      => ['required', Rule::in(array_keys(MutualProfile::CATEGORIES))],
            'tagline'       => ['nullable', 'string', 'max:180'],
            'description'   => ['nullable', 'string', 'max:5000'],
            'mission'       => ['nullable', 'string', 'max:2000'],
            'coverage_zone' => ['nullable', 'string', 'max:255'],
            'services'      => ['nullable', 'string', 'max:1000'],
            'phone'         => ['nullable', 'string', 'max:40'],
            'email'         => ['nullable', 'email:rfc', 'max:180'],
            'alert_email'   => ['nullable', 'email:rfc', 'max:180'],
            'website'       => ['nullable', 'url:https,http', 'max:255'],
            'address'       => ['nullable', 'string', 'max:255'],
            'logo'          => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp,gif', 'max:' . $maxKb],
            'cover'         => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp,gif', 'max:' . $maxKb],
            'remove_logo'   => ['nullable', 'boolean'],
            'remove_cover'  => ['nullable', 'boolean'],
        ]);

        $profile = MutualProfile::firstOrNew(['mutual_id' => $mutual->id]);
        $old = ['logo_path' => $profile->logo_path, 'cover_path' => $profile->cover_path];
        $profile->fill(collect($data)->except(['logo', 'cover', 'remove_logo', 'remove_cover', 'services'])->all());
        $profile->services = collect(preg_split('/[,;\n]+/', (string) ($data['services'] ?? '')))
            ->map(fn ($s) => trim($s))->filter()->unique()->take(20)->values()->all();

        foreach (['logo' => 512, 'cover' => 1600] as $field => $side) {
            $col = "{$field}_path";
            if ($request->hasFile($field)) {
                $img = $this->media->storeImage($request->file($field), "social/mutuals/{$mutual->id}", $side);
                $this->media->delete($img['thumb_path']);
                $profile->{$col} = $img['path'];
            } elseif ($request->boolean("remove_{$field}")) {
                $profile->{$col} = null;
            }
        }
        $profile->save();
        foreach ($old as $col => $path) {
            if ($path && $path !== $profile->{$col}) {
                $this->media->delete($path);
            }
        }
        cache()->forget('social:places');

        return redirect()->route('social-presence.index')->with('success', 'Page publique mise à jour.');
    }

    /** Comptes du réseau social autorisés à publier au nom de la mutuelle depuis le réseau. */
    public function addManager(Request $request)
    {
        $mutual = $this->authorizeMutual($request);
        $data = $request->validate(['manager' => ['required', 'string', 'max:180'], 'role' => ['required', Rule::in(array_keys(\App\Models\Social\PageAdmin::ROLES))]],
            ['manager.required' => 'Indiquez le téléphone ou l’e-mail du compte MX-CONNECT.']);
        $admin = \App\Services\Social\PageService::addManager($mutual, $data['manager'], $data['role']);

        return redirect()->route('social-presence.profile')->with('success', $admin->account->name . ' peut publier au nom de la mutuelle sur le réseau social.');
    }

    public function removeManager(Request $request, int $admin)
    {
        $mutual = $this->authorizeMutual($request);
        \App\Models\Social\PageAdmin::where('mutual_id', $mutual->id)->findOrFail($admin)->delete();

        return redirect()->route('social-presence.profile')->with('success', 'Gestionnaire retiré.');
    }

    // --- Publications ---

    public function create(Request $request)
    {
        $this->authorizeMutual($request);

        return view('tenant.social.form', ['post' => new Post(['type' => 'annonce', 'visibility' => 'public', 'status' => 'published'])]);
    }

    public function store(Request $request)
    {
        $mutual = $this->authorizeMutual($request);
        $data = $this->validatePost($request);

        $post = $this->posts->create($data, $request->file('images', []), null, $mutual,
            $request->user()->id, $request->user()->name);

        return redirect()->route('social-presence.index')->with('success',
            $post->status === 'published' ? 'Publication en ligne.' : 'Brouillon enregistré.');
    }

    public function edit(Request $request, int $post)
    {
        $mutual = $this->authorizeMutual($request);

        return view('tenant.social.form', ['post' => $this->ownPost($mutual, $post)->load('media')]);
    }

    public function update(Request $request, int $post)
    {
        $mutual = $this->authorizeMutual($request);
        $post = $this->ownPost($mutual, $post)->load('media');
        $data = $this->validatePost($request, $post);

        $remove = $post->media->whereIn('id', array_map('intval', (array) $request->input('remove_media', [])));
        $remaining = $post->media->count() - $remove->count();
        $newFiles = $request->file('images', []);
        $max = (int) config('mxconnect.social.max_images', 6);
        if ($remaining + count($newFiles) > $max) {
            return back()->withInput()->withErrors(['images' => "Maximum {$max} images par publication."]);
        }
        if (trim((string) $data['body']) === '' && $remaining + count($newFiles) === 0) {
            return back()->withInput()->withErrors(['body' => 'Écrivez un message ou gardez au moins une image.']);
        }

        $stored = [];
        foreach ($newFiles as $f) {
            $stored[] = $this->media->storeImage($f, 'social/posts');
        }
        DB::connection('central')->transaction(function () use ($post, $data, $remove, $stored, $remaining) {
            $post->update([
                'type' => $data['type'], 'category' => $data['category'], 'body' => $data['body'],
                'link_url' => $data['link_url'], 'event_starts_at' => $data['event_starts_at'],
                'event_location' => $data['event_location'], 'visibility' => $data['visibility'],
            ]);
            PostMedia::whereIn('id', $remove->pluck('id'))->delete();
            foreach ($stored as $i => $m) {
                PostMedia::create($m + ['post_id' => $post->id, 'position' => $remaining + $i]);
            }
        });
        $this->media->delete(...$remove->flatMap(fn ($m) => [$m->path, $m->thumb_path])->all());
        if ($data['status'] !== $post->status) {
            $this->posts->setStatus($post, $data['status']);
        }

        return redirect()->route('social-presence.index')->with('success', 'Publication mise à jour.');
    }

    public function status(Request $request, int $post)
    {
        $mutual = $this->authorizeMutual($request);
        $post = $this->ownPost($mutual, $post);
        $data = $request->validate(['status' => ['required', 'in:published,draft,archived']]);
        abort_if($post->status === 'hidden', 403, 'Publication masquée par la modération du réseau.');
        $this->posts->setStatus($post, $data['status']);

        return back()->with('success', 'Statut mis à jour.');
    }

    public function destroy(Request $request, int $post)
    {
        $mutual = $this->authorizeMutual($request);
        $this->posts->delete($this->ownPost($mutual, $post));

        return redirect()->route('social-presence.index')->with('success', 'Publication supprimée.');
    }

    /** Masquer un commentaire sous une publication de la mutuelle. */
    public function hideComment(Request $request, int $comment)
    {
        $mutual = $this->authorizeMutual($request);
        // Commentaires sous les publications de la mutuelle OU dans ses groupes.
        $c = Comment::whereHas('post', fn ($q) => $q->where('mutual_id', $mutual->id)
            ->orWhereIn('group_id', \App\Models\Social\Group::where('mutual_id', $mutual->id)->select('id')))->findOrFail($comment);
        $c->update(['status' => 'hidden']);
        $c->post->forceFill(['comments_count' => Comment::where('post_id', $c->post_id)->where('status', 'visible')->count()])->save();

        return back()->with('success', 'Commentaire masqué.');
    }

    public function comments(Request $request, int $post)
    {
        $mutual = $this->authorizeMutual($request);
        $post = Post::where(fn ($q) => $q->where('mutual_id', $mutual->id)
            ->orWhereIn('group_id', \App\Models\Social\Group::where('mutual_id', $mutual->id)->select('id')))->findOrFail($post);
        $comments = Comment::where('post_id', $post->id)->where('status', 'visible')->with('account')->latest()->paginate(50);

        return view('tenant.social.comments', compact('post', 'comments'));
    }

    // --- Messages reçus ---

    public function messages(Request $request)
    {
        $mutual = $this->authorizeMutual($request);
        $messages = ContactRequest::where('mutual_id', $mutual->id)
            ->orderByRaw("status = 'new' desc")->latest()->paginate(25);

        return view('tenant.social.messages', compact('messages'));
    }

    public function messageStatus(Request $request, int $message)
    {
        $mutual = $this->authorizeMutual($request);
        $msg = ContactRequest::where('mutual_id', $mutual->id)->findOrFail($message);
        $msg->update($request->validate(['status' => ['required', 'in:new,read,closed']]));

        return back()->with('success', 'Message mis à jour.');
    }

    // --- Outils ---

    private function authorizeMutual(Request $request): Mutual
    {
        abort_unless($request->user()?->hasAnyRole(self::ROLES), 403,
            'Réservé aux rôles « administrateur de la mutuelle » et « community manager ».');
        /** @var Mutual $mutual */
        $mutual = tenant();
        $mutual->loadMissing('profile');

        return $mutual;
    }

    private function ownPost(Mutual $mutual, int $id): Post
    {
        return Post::where('mutual_id', $mutual->id)->findOrFail($id);
    }

    private function validatePost(Request $request, ?Post $post = null): array
    {
        $maxKb = (int) config('mxconnect.social.max_upload_kb', 6144);
        $data = $request->validate([
            'type'            => ['required', Rule::in(self::POST_TYPES)],
            'category'        => ['nullable', Rule::in(array_keys(Post::ACTIVITY_CATEGORIES))],
            'body'            => ['nullable', 'string', 'max:5000'],
            'link_url'        => ['nullable', 'url:https,http', 'max:500', 'required_if:type,lien'],
            'event_starts_at' => ['nullable', 'date', 'required_if:type,evenement'],
            'event_location'  => ['nullable', 'string', 'max:180'],
            'visibility'      => ['required', 'in:public,followers'],
            'status'          => ['required', 'in:published,draft'],
            'images'          => ['nullable', 'array', 'max:' . config('mxconnect.social.max_images', 6)],
            'images.*'        => ['file', 'mimes:jpeg,jpg,png,webp,gif', 'max:' . $maxKb],
            'medical_ack'     => ['accepted'],
        ], [
            'link_url.required_if'        => 'Indiquez le lien à partager.',
            'event_starts_at.required_if' => 'Indiquez la date et l’heure de l’événement.',
            'medical_ack.accepted'        => 'Confirmez que la publication ne contient aucune donnée médicale personnelle.',
        ]);
        if ($post && $post->status === 'hidden') {
            $data['status'] = 'hidden';   // la modération du réseau garde la main
        }

        return [
            'type' => $data['type'], 'category' => $data['category'] ?? null,
            'body' => isset($data['body']) ? trim($data['body']) : null,
            'link_url' => $data['link_url'] ?? null,
            'event_starts_at' => $data['type'] === 'evenement' ? ($data['event_starts_at'] ?? null) : null,
            'event_location' => $data['type'] === 'evenement' ? ($data['event_location'] ?? null) : null,
            'visibility' => $data['visibility'], 'status' => $data['status'],
        ];
    }

    /** Adresse publique sur le domaine central (le back-office est sur le sous-domaine). */
    private function publicUrl(string $path): string
    {
        return rtrim((string) config('app.url'), '/') . $path;
    }
}
