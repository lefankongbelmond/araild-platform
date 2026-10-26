<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Http\Requests\Social\EngagementRequestForm;
use App\Http\Requests\Social\SupportOfferForm;
use App\Models\Central\Organization;
use App\Models\Central\Post;
use App\Models\Central\Project;
use App\Services\Social\RequestService;
use Illuminate\Http\Request;

/** Actions A ("Je m'engage") and C ("Soutenir ou devenir partenaire") — requester side. */
class RequestController extends Controller
{
    public function __construct(private RequestService $requests) {}

    public function engageForm(Request $request, Organization $organization)
    {
        return $this->form($request, $organization, 'engagement');
    }

    public function engage(EngagementRequestForm $request, Organization $organization)
    {
        $this->requests->submitEngagement($request->user('member'), $organization, $request->validated());

        return redirect()->route('my.requests')->with('success', __('mxconnect.social.requests.engagement_sent', ['name' => $organization->name]));
    }

    public function supportForm(Request $request, Organization $organization)
    {
        return $this->form($request, $organization, 'support');
    }

    public function support(SupportOfferForm $request, Organization $organization)
    {
        $this->requests->submitSupport($request->user('member'), $organization, $request->validated());

        return redirect()->route('my.requests')->with('success', __('mxconnect.social.requests.support_sent', ['name' => $organization->name]));
    }

    private function form(Request $request, Organization $organization, string $kind)
    {
        $viewer = $request->user('member');
        abort_unless($organization->isPublished(), 404);

        $post = $request->filled('publication')
            ? Post::where('organization_id', $organization->getKey())->find($request->query('publication'))
            : null;
        $project = $request->filled('projet')
            ? Project::where('organization_id', $organization->getKey())->find($request->query('projet'))
            : $post?->project;

        return view('social.requests.form', [
            'kind' => $kind,
            'organization' => $organization,
            'post' => $post && $post->isVisibleTo($viewer) ? $post : null,
            'project' => $project,
            'isManager' => $organization->roleOf($viewer) !== null,
        ]);
    }
}
