<?php

namespace App\Http\Controllers\Social;

use App\Models\Social\Group;
use App\Models\Social\GroupMember;
use App\Services\Social\FeedService;
use App\Services\Social\PostService;
use Illuminate\Http\Request;

/** Groupes communautaires (côté membres). */
class GroupController extends SocialController
{
    public function __construct(FeedService $feed, private PostService $posts)
    {
        parent::__construct($feed);
    }

    public function index(Request $request)
    {
        $viewer = $this->viewer();
        $q = $request->string('q')->trim()->limit(100, '')->toString();
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';

        $groups = Group::where('status', 'active')
            ->whereHas('mutual', fn ($m) => $m->where('status', '!=', 'suspended'))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', $like)->orWhere('description', 'like', $like)))
            ->with('mutual.profile')->orderByDesc('members_count')->orderBy('name')->paginate(18)->withQueryString();

        $mine = $viewer ? GroupMember::where('public_account_id', $viewer->id)->pluck('status', 'group_id')->all() : [];

        return view('social.groups.index', compact('groups', 'mine', 'q'));
    }

    public function show(Request $request, Group $group)
    {
        abort_if($group->mutual?->status === 'suspended', 404);
        $viewer = $this->viewer();
        $membership = $group->membership($viewer);
        $tab = in_array($request->get('onglet'), ['a-propos', 'membres'], true) ? $request->get('onglet') : 'publications';
        $data = ['group' => $group->load('mutual.profile'), 'membership' => $membership, 'tab' => $tab,
                 'canRead' => $group->canRead($viewer), 'isMember' => $membership?->status === 'active', 'inGroup' => true];

        if ($tab === 'membres') {
            $data['members'] = $data['isMember']
                ? GroupMember::where('group_id', $group->id)->where('status', 'active')->with('account')->latest()->paginate(40)
                : null;
        }
        if ($tab === 'publications' && $data['canRead']) {
            return $this->feedView($request, 'social.groups.show', $this->feed->forGroup($group, $viewer), $data);
        }
        $state = $this->feed->viewerState($viewer, []);

        return view('social.groups.show', $data + ['posts' => null, 'state' => $state, 'followMap' => $state['follows']]);
    }

    public function join(Group $group)
    {
        abort_unless($group->status === 'active' && $group->mutual?->status !== 'suspended', 422, 'Ce groupe n’accepte plus de membres.');
        $viewer = $this->viewer();
        $m = GroupMember::firstOrCreate(
            ['group_id' => $group->id, 'public_account_id' => $viewer->id],
            ['status' => $group->isPrivate() ? 'pending' : 'active'],
        );
        $group->refreshCounters();
        if ($m->wasRecentlyCreated && $m->status === 'pending' && ($mutual = $group->mutual()->with('profile')->first())) {
            app(\App\Services\Social\MailService::class)->alertMutual($mutual, 'Demande pour rejoindre un groupe',
                "Un membre demande à rejoindre le groupe « {$group->name} ».", '/reseau-social/groupes/' . $group->id . '/membres');
        }

        return back()->with('ok', $m->status === 'active'
            ? "Vous avez rejoint « {$group->name} »."
            : "Demande envoyée : {$group->mutual?->name} doit approuver votre arrivée dans ce groupe privé.");
    }

    public function leave(Group $group)
    {
        GroupMember::where('group_id', $group->id)->where('public_account_id', $this->viewer()->id)->delete();
        $group->refreshCounters();

        return redirect()->route('social.group', $group)->with('ok', "Vous avez quitté « {$group->name} ».");
    }

    public function post(Request $request, Group $group)
    {
        abort_unless($group->status === 'active' && $group->isMember($this->viewer()), 403, 'Rejoignez le groupe pour publier.');
        $maxKb = (int) config('mxconnect.social.max_upload_kb', 6144);
        $data = $request->validate([
            'body'     => ['nullable', 'string', 'max:5000'],
            'images'   => ['nullable', 'array', 'max:' . config('mxconnect.social.max_images', 6)],
            'images.*' => ['file', 'mimes:jpeg,jpg,png,webp,gif', 'max:' . $maxKb],
            'mentions'   => ['nullable', 'array', 'max:' . \App\Services\Social\MentionService::MAX],
            'mentions.*' => ['string', 'max:80'],
        ]);
        $post = $this->posts->create(
            ['body' => $data['body'] ?? null, 'type' => 'texte', 'visibility' => 'public', 'group_id' => $group->id, 'mentions' => $data['mentions'] ?? []],
            $request->file('images', []), $this->viewer(),
        );

        $url = route('social.group', $group) . '#post-' . $post->id;
        if ($request->expectsJson()) {
            session()->flash('ok', 'Publié dans le groupe.');

            return response()->json(['ok' => true, 'url' => $url]);
        }

        return redirect()->to($url)->with('ok', 'Publié dans le groupe.');
    }
}
