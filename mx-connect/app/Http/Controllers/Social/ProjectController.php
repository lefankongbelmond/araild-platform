<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Http\Requests\Social\ProjectRequest;
use App\Models\Central\Organization;
use App\Models\Central\Post;
use App\Models\Central\Project;
use App\Services\Social\OrganizationService;
use App\Services\Social\ProjectService;
use Illuminate\Http\Request;

/** Action B — "Voir le projet et les résultats". */
class ProjectController extends Controller
{
    public function __construct(
        private ProjectService $projects,
        private OrganizationService $organizations,
    ) {}

    public function show(Request $request, Project $project)
    {
        $viewer = $request->user('member');
        abort_unless($project->isVisibleTo($viewer), 404);

        $organization = $project->organization;

        return view('social.projects.show', [
            'viewer' => $viewer,
            'project' => $project,
            'organization' => $organization,
            'isAdmin' => $organization->isAdministeredBy($viewer),
            'isManager' => $organization->roleOf($viewer) !== null,
            'indicators' => $project->indicators()->orderByDesc('is_verified')->oldest()->get(),
            'updates' => $project->updates()->latest('published_at')->get(),
            'relatedPosts' => Post::visibleTo($viewer)->where('posts.project_id', $project->getKey())
                ->with('organization')->latest('published_at')->limit(5)->get(),
        ]);
    }

    public function create(Request $request, Organization $organization)
    {
        $this->organizations->authorizeAdmin($organization, $request->user('member'));

        return view('social.projects.form', ['organization' => $organization, 'project' => null]);
    }

    public function store(ProjectRequest $request, Organization $organization)
    {
        $project = $this->projects->create($organization, $request->user('member'), $request->validated(), $request->file('cover'));

        return redirect()->route('projects.show', $project)->with('success', __('mxconnect.social.project.created'));
    }

    public function edit(Request $request, Project $project)
    {
        $this->organizations->authorizeAdmin($project->organization, $request->user('member'));

        return view('social.projects.form', ['organization' => $project->organization, 'project' => $project]);
    }

    public function update(ProjectRequest $request, Project $project)
    {
        $this->projects->update($project, $request->user('member'), $request->validated(), $request->file('cover'));

        return redirect()->route('projects.show', $project)->with('success', __('mxconnect.social.project.updated'));
    }

    public function storeIndicator(Request $request, Project $project)
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:200'],
            'value' => ['required', 'string', 'max:100'],
            'unit' => ['nullable', 'string', 'max:40'],
            'source_note' => ['nullable', 'string', 'max:255'],
        ]);

        $this->projects->addIndicator($project, $request->user('member'), $data);

        return redirect()->to(route('projects.show', $project).'#resultats')->with('success', __('mxconnect.social.project.indicator_added'));
    }

    public function storeUpdate(Request $request, Project $project)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $this->projects->addUpdate($project, $request->user('member'), $data);

        return redirect()->to(route('projects.show', $project).'#actualites')->with('success', __('mxconnect.social.project.update_added'));
    }
}
