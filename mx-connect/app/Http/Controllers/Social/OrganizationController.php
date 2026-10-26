<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Http\Requests\Social\OrganizationRequest;
use App\Models\Central\Country;
use App\Models\Central\Organization;
use App\Models\Central\Post;
use App\Models\Central\PostLike;
use App\Services\Social\OrganizationService;
use Illuminate\Http\Request;

/** Module 2 — organization profiles. */
class OrganizationController extends Controller
{
    public const TABS = ['publications', 'projets', 'a-propos'];

    public function __construct(private OrganizationService $organizations) {}

    public function show(Request $request, Organization $organization)
    {
        $viewer = $request->user('member');
        abort_unless($organization->isVisibleTo($viewer), 404);

        $tab = in_array($request->query('onglet'), self::TABS, true) ? $request->query('onglet') : 'publications';
        $role = $organization->roleOf($viewer);

        $posts = Post::visibleTo($viewer)->where('posts.organization_id', $organization->getKey())
            ->with(['organization', 'author', 'project', 'hashtags'])
            ->orderByDesc('pinned_at')->latest('published_at')
            ->paginate(10, ['*'], 'page')->withQueryString();

        $likedIds = $viewer
            ? PostLike::where('public_account_id', $viewer->getKey())->whereIn('post_id', $posts->getCollection()->modelKeys())->pluck('post_id')->all()
            : [];

        return view('social.organizations.show', [
            'viewer' => $viewer,
            'organization' => $organization->load('country'),
            'tab' => $tab,
            'role' => $role,
            'isAdmin' => $organization->isAdministeredBy($viewer),
            'following' => $viewer ? $organization->followers()->whereKey($viewer->getKey())->exists() : false,
            'posts' => $posts,
            'likedIds' => $likedIds,
            'roles' => $viewer?->organizationRoles() ?? [],
            'projects' => $organization->projects()->latest()->get(),
            'counts' => [
                'projects' => $organization->projects()->count(),
                'posts' => Post::visibleTo(null)->where('posts.organization_id', $organization->getKey())->count(),
            ],
        ]);
    }

    public function create()
    {
        return view('social.organizations.form', [
            'organization' => null,
            'countries' => Country::where('active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(OrganizationRequest $request)
    {
        $org = $this->organizations->create(
            $request->user('member'),
            $request->validated(),
            $request->file('logo'),
            $request->file('cover'),
        );

        return redirect()->route('organizations.show', $org)->with('success', __('mxconnect.social.org.created'));
    }

    public function edit(Request $request, Organization $organization)
    {
        $this->organizations->authorizeAdmin($organization, $request->user('member'));

        return view('social.organizations.form', [
            'organization' => $organization,
            'countries' => Country::where('active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(OrganizationRequest $request, Organization $organization)
    {
        $this->organizations->update(
            $organization,
            $request->user('member'),
            $request->validated(),
            $request->file('logo'),
            $request->file('cover'),
        );

        return redirect()->route('organizations.show', $organization)->with('success', __('mxconnect.social.org.updated'));
    }

    public function follow(Request $request, Organization $organization)
    {
        $this->organizations->follow($organization, $request->user('member'));

        return back()->with('success', __('mxconnect.social.org.followed', ['name' => $organization->name]));
    }

    public function unfollow(Request $request, Organization $organization)
    {
        $this->organizations->unfollow($organization, $request->user('member'));

        return back();
    }
}
