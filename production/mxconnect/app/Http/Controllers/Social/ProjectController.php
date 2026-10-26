<?php

namespace App\Http\Controllers\Social;

use App\Models\Social\Opportunity;
use App\Models\Social\Project;
use App\Services\Social\PageService;
use Illuminate\Http\Request;

/**
 * Projets publics des pages du réseau (mutuelles et pages créées par les membres) :
 * présentation, résultats déclarés ou vérifiés, besoins, et les actions
 * « Je m'engage » / « Soutenir ou devenir partenaire ».
 */
class ProjectController extends SocialController
{
    public const NEEDS = ['benevoles' => 'Cherche des bénévoles', 'partenaires' => 'Cherche des partenaires'];

    public function index(Request $request)
    {
        $q = $request->string('q')->trim()->limit(100, '')->toString();
        $region = $request->string('region')->limit(120, '')->toString();
        $phase = array_key_exists((string) $request->get('etat'), Project::PHASES) ? $request->get('etat') : null;
        $need = array_key_exists((string) $request->get('besoin'), self::NEEDS) ? $request->get('besoin') : null;
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';

        $projects = Project::public()->with(['mutual.profile'])->withCount('indicators')
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('title', 'like', $like)->orWhere('summary', 'like', $like)->orWhere('zone', 'like', $like)))
            ->when($region, fn ($w) => $w->where('region', $region))
            ->when($phase, fn ($w) => $w->where('phase', $phase))
            ->when($need === 'benevoles', fn ($w) => $w->where('seeks_volunteers', true))
            ->when($need === 'partenaires', fn ($w) => $w->where('seeks_partners', true))
            ->orderByRaw("phase = 'completed'")->orderByDesc('published_at')->orderByDesc('id')
            ->paginate(12)->withQueryString();

        return view('social.projects.index', [
            'projects' => $projects, 'q' => $q, 'region' => $region, 'phase' => $phase, 'need' => $need,
            'regions' => Project::public()->whereNotNull('region')->distinct()->orderBy('region')->pluck('region'),
        ]);
    }

    public function show(Project $project)
    {
        $viewer = $this->viewer();
        $project->load(['mutual.profile', 'mutual.country', 'indicators', 'updates', 'documents']);
        $canManage = PageService::canPublish($viewer, $project->mutual);
        // Brouillon ou projet masqué : visible seulement par les gestionnaires de la page.
        abort_unless($project->isPublic() || $canManage, 404);

        return view('social.projects.show', [
            'project' => $project, 'page' => $project->mutual, 'canManage' => $canManage,
            'opportunities' => Opportunity::public()->open()->where('project_id', $project->id)->orderBy('deadline_on')->get(),
            'isDemo' => $project->is_demo || $project->mutual->profile?->is_demo,
            'related' => \App\Models\Social\Post::publicFeed()->where('project_id', $project->id)->latest('published_at')->limit(4)->get(),
        ]);
    }
}
