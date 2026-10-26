<?php

namespace App\Http\Controllers\Social;

use App\Enums\PostType;
use App\Http\Controllers\Controller;
use App\Models\Central\Organization;
use App\Models\Central\Post;
use App\Models\Central\Project;
use App\Models\Central\PublicAccount;
use Illuminate\Http\Request;

/** Module 1 — public home. Every figure shown is a live count; nothing is illustrative. */
class HomeController extends Controller
{
    public function index(Request $request)
    {
        $viewer = $request->user('member');

        $domains = collect(config('mxconnect.social.domains'))
            ->mapWithKeys(fn ($d) => [$d => Organization::published()->inDomain($d)->count()]);

        return view('social.home', [
            'viewer' => $viewer,
            'domains' => $domains,
            'stats' => [
                'organizations' => Organization::published()->count(),
                'members' => PublicAccount::count(),
                'projects' => Project::ofPublishedOrganizations()->count(),
                'events' => Post::visibleTo(null)->where('type', PostType::Event->value)->where('event_starts_at', '>=', now())->count(),
            ],
            'featuredProjects' => Project::ofPublishedOrganizations()->with('organization')->latest()->limit(4)->get(),
            'recentPosts' => Post::visibleTo($viewer)->with(['organization', 'author', 'project'])->latest('published_at')->limit(3)->get(),
            'suggestedOrganizations' => Organization::published()->orderByDesc('followers_count')->latest()->limit(5)->get(),
            'upcomingEvents' => Post::visibleTo($viewer)->with('organization')->where('type', PostType::Event->value)
                ->where('event_starts_at', '>=', now())->orderBy('event_starts_at')->limit(3)->get(),
        ]);
    }
}
