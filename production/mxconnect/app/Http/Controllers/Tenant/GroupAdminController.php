<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Central\Mutual;
use App\Models\Social\Group;
use App\Models\Social\GroupMember;
use App\Models\Social\Post;
use App\Services\Social\MediaService;
use App\Services\Social\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Groupes animés par la mutuelle : création, approbation des membres,
 * modération des publications. Rôles : administrateur et community manager.
 * Une mutuelle n'agit que sur SES groupes.
 */
class GroupAdminController extends Controller
{
    public const ROLES = ['mutual_admin', 'community_manager'];

    public function __construct(private MediaService $media, private NotificationService $notifications) {}

    public function index(Request $request)
    {
        $mutual = $this->authorizeMutual($request);
        $groups = Group::where('mutual_id', $mutual->id)
            ->withCount(['members as pending_count' => fn ($q) => $q->where('status', 'pending')])
            ->orderBy('status')->orderBy('name')->get();

        return view('tenant.groups.index', ['groups' => $groups, 'central' => rtrim(config('app.url'), '/')]);
    }

    public function create(Request $request)
    {
        $this->authorizeMutual($request);

        return view('tenant.groups.form', ['group' => new Group(['visibility' => 'public'])]);
    }

    public function store(Request $request)
    {
        $mutual = $this->authorizeMutual($request);
        $data = $this->validated($request);
        $group = new Group($data + ['mutual_id' => $mutual->id, 'slug' => Group::uniqueSlug($data['name'])]);
        $this->cover($request, $group, $mutual);
        $group->save();

        return redirect()->route('social-groups.index')->with('success', "Groupe « {$group->name} » créé.");
    }

    public function edit(Request $request, int $group)
    {
        $mutual = $this->authorizeMutual($request);

        return view('tenant.groups.form', ['group' => $this->own($mutual, $group)]);
    }

    public function update(Request $request, int $group)
    {
        $mutual = $this->authorizeMutual($request);
        $g = $this->own($mutual, $group);
        $g->fill($this->validated($request));
        $this->cover($request, $g, $mutual);
        $g->save();

        return redirect()->route('social-groups.index')->with('success', 'Groupe mis à jour.');
    }

    public function archive(Request $request, int $group)
    {
        $mutual = $this->authorizeMutual($request);
        $g = $this->own($mutual, $group);
        $g->update(['status' => $g->status === 'active' ? 'archived' : 'active']);

        return back()->with('success', $g->status === 'archived' ? 'Groupe archivé (lecture seule).' : 'Groupe réactivé.');
    }

    public function members(Request $request, int $group)
    {
        $mutual = $this->authorizeMutual($request);
        $g = $this->own($mutual, $group);

        return view('tenant.groups.members', [
            'group'   => $g,
            'pending' => GroupMember::where('group_id', $g->id)->where('status', 'pending')->with('account')->oldest()->get(),
            'active'  => GroupMember::where('group_id', $g->id)->where('status', 'active')->with('account')->latest()->paginate(50),
        ]);
    }

    public function decideMember(Request $request, int $group, int $member)
    {
        $mutual = $this->authorizeMutual($request);
        $g = $this->own($mutual, $group);
        $m = GroupMember::where('group_id', $g->id)->findOrFail($member);
        $action = $request->validate(['action' => ['required', 'in:approve,reject,remove']])['action'];

        if ($action === 'approve') {
            $m->update(['status' => 'active']);
            $this->notifications->notify($m->public_account_id, 'group', "Bienvenue dans le groupe « {$g->name} »",
                'Votre demande a été acceptée.', '/groupes/' . $g->slug);
        } else {
            $m->delete();
        }
        $g->refreshCounters();

        return back()->with('success', ['approve' => 'Membre accepté.', 'reject' => 'Demande refusée.', 'remove' => 'Membre retiré du groupe.'][$action]);
    }

    public function posts(Request $request, int $group)
    {
        $mutual = $this->authorizeMutual($request);
        $g = $this->own($mutual, $group);

        return view('tenant.groups.posts', [
            'group' => $g, 'central' => rtrim(config('app.url'), '/'),
            'posts' => Post::where('group_id', $g->id)->whereIn('status', ['published', 'hidden'])->with(['account', 'media'])->latest('published_at')->paginate(30),
        ]);
    }

    /** Masquer / réafficher une publication du groupe. */
    public function togglePost(Request $request, int $group, int $post)
    {
        $mutual = $this->authorizeMutual($request);
        $g = $this->own($mutual, $group);
        $p = Post::where('group_id', $g->id)->findOrFail($post);
        $p->update(['status' => $p->status === 'hidden' ? 'published' : 'hidden']);
        $g->refreshCounters();

        return back()->with('success', $p->status === 'hidden' ? 'Publication masquée.' : 'Publication réaffichée.');
    }

    private function validated(Request $request): array
    {
        return collect($request->validate([
            'name'        => ['required', 'string', 'max:120'],
            'topic'       => ['nullable', Rule::in(array_keys(Post::ACTIVITY_CATEGORIES))],
            'description' => ['nullable', 'string', 'max:3000'],
            'rules'       => ['nullable', 'string', 'max:3000'],
            'visibility'  => ['required', 'in:public,private'],
            'cover'       => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp,gif', 'max:' . config('mxconnect.social.max_upload_kb', 6144)],
        ]))->except('cover')->all();
    }

    private function cover(Request $request, Group $g, Mutual $mutual): void
    {
        if ($request->hasFile('cover')) {
            $img = $this->media->storeImage($request->file('cover'), "social/groups/{$mutual->id}", 1600);
            $this->media->delete($img['thumb_path'], $g->cover_path);
            $g->cover_path = $img['path'];
        }
    }

    private function own(Mutual $mutual, int $id): Group
    {
        return Group::where('mutual_id', $mutual->id)->findOrFail($id);
    }

    private function authorizeMutual(Request $request): Mutual
    {
        abort_unless($request->user()?->hasAnyRole(self::ROLES), 403);

        return tenant();
    }
}
