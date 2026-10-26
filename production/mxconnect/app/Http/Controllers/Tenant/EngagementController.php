<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Central\Country;
use App\Models\Central\Mutual;
use App\Models\Social\Opportunity;
use App\Models\Social\Project;
use App\Models\Social\ProjectIndicator;
use App\Models\Social\SocialRequest;
use App\Services\Social\ProjectService;
use App\Services\Social\RequestService;
use Illuminate\Http\Request;

/**
 * Back-office de la mutuelle : projets (et résultats), emplois et opportunités publiés
 * sur le réseau, et demandes reçues (« Je m'engage », soutien, candidatures).
 * Rôles : administrateur et community manager ; les CANDIDATURES (CV, données
 * personnelles) sont réservées à l'administrateur. La mutuelle n'agit que sur ses contenus.
 */
class EngagementController extends Controller
{
    public const ROLES = ['mutual_admin', 'community_manager'];
    public const TABS = ['demandes' => 'Demandes reçues', 'projets' => 'Projets', 'opportunites' => 'Emplois et opportunités'];

    public function __construct(private ProjectService $projects, private RequestService $requests) {}

    public function index(Request $request)
    {
        $mutual = $this->authorizeMutual($request);
        $tab = array_key_exists((string) $request->get('onglet'), self::TABS) ? $request->get('onglet') : 'demandes';
        $data = ['mutual' => $mutual, 'tab' => $tab, 'publicBase' => $this->publicUrl('')];

        if ($tab === 'projets') {
            $data['items'] = Project::where('mutual_id', $mutual->id)->withCount('indicators')->latest('id')->paginate(20)->withQueryString();
        } elseif ($tab === 'opportunites') {
            $data['items'] = Opportunity::where('mutual_id', $mutual->id)->latest('id')->paginate(20)->withQueryString();
        } else {
            $status = array_key_exists((string) $request->get('statut'), SocialRequest::STATUSES) ? $request->get('statut') : null;
            $data['status'] = $status;
            $data['items'] = $this->visibleRequests($request, $mutual)->with(['project', 'opportunity'])
                ->when($status, fn ($q) => $q->where('status', $status), fn ($q) => $q->whereIn('status', [...SocialRequest::OPEN, 'accepted']))
                ->latest('id')->paginate(25)->withQueryString();
            $data['canApplications'] = $request->user()->hasRole('mutual_admin');
        }

        return view('tenant.engagement.index', $data);
    }

    // --- Demandes ---

    public function showRequest(Request $request, string $reference)
    {
        $mutual = $this->authorizeMutual($request);
        $r = $this->findRequest($request, $mutual, $reference)->load(['project', 'opportunity', 'events']);

        return view('tenant.engagement.request', ['r' => $r, 'transitions' => RequestService::TRANSITIONS[$r->status] ?? []]);
    }

    public function decide(Request $request, string $reference)
    {
        $mutual = $this->authorizeMutual($request);
        $r = $this->findRequest($request, $mutual, $reference);
        $data = $request->validate(['status' => ['required', 'in:in_review,accepted,rejected,closed'], 'note' => ['nullable', 'string', 'max:2000']]);
        $this->requests->decide($r, $data['status'], $data['note'] ?? null, $request->user()->name . ' (' . $mutual->name . ')');

        return back()->with('success', 'Demande mise à jour : ' . SocialRequest::STATUSES[$data['status']] . '.');
    }

    public function cv(Request $request, string $reference)
    {
        $mutual = $this->authorizeMutual($request);

        return $this->requests->cvResponse($this->findRequest($request, $mutual, $reference));
    }

    // --- Projets ---

    public function createProject(Request $request)
    {
        $mutual = $this->authorizeMutual($request);

        return view('tenant.engagement.project-form', ['mutual' => $mutual, 'project' => new Project(['phase' => 'ongoing', 'status' => 'published', 'region' => $mutual->region, 'city' => $mutual->city])]);
    }

    public function storeProject(Request $request)
    {
        $mutual = $this->authorizeMutual($request);
        $data = $request->validate(ProjectService::projectRules() + ['medical_ack' => ['accepted']], ProjectService::projectMessages());
        $p = $this->projects->saveProject($mutual, $data, $request->file('cover'), null, null, $request->user()->id, $request->user()->name);

        return redirect()->route('engagement.projects.edit', $p->id)->with('success', 'Projet enregistré. Ajoutez maintenant ses résultats chiffrés.');
    }

    public function editProject(Request $request, int $project)
    {
        $mutual = $this->authorizeMutual($request);

        return view('tenant.engagement.project-form', ['mutual' => $mutual, 'project' => $this->project($mutual, $project)->load(['indicators', 'updates'])]);
    }

    public function updateProject(Request $request, int $project)
    {
        $mutual = $this->authorizeMutual($request);
        $p = $this->project($mutual, $project);
        $data = $request->validate(ProjectService::projectRules() + ['medical_ack' => ['accepted']], ProjectService::projectMessages());
        $this->projects->saveProject($mutual, $data, $request->file('cover'), $p, null, $request->user()->id, $request->user()->name);

        return redirect()->route('engagement.projects.edit', $p->id)->with('success', 'Projet mis à jour.');
    }

    public function destroyProject(Request $request, int $project)
    {
        $mutual = $this->authorizeMutual($request);
        $this->projects->deleteProject($this->project($mutual, $project));

        return redirect()->route('engagement.index', ['onglet' => 'projets'])->with('success', 'Projet supprimé.');
    }

    public function storeIndicator(Request $request, int $project)
    {
        $mutual = $this->authorizeMutual($request);
        $p = $this->project($mutual, $project);
        $this->projects->saveIndicator($p, $request->validateWithBag('indicator', ProjectService::indicatorRules()));

        return redirect()->to(route('engagement.projects.edit', $p->id) . '#resultats')->with('success', 'Résultat ajouté (déclaré par la mutuelle).');
    }

    public function updateIndicator(Request $request, int $project, int $indicator)
    {
        $mutual = $this->authorizeMutual($request);
        $p = $this->project($mutual, $project);
        $i = ProjectIndicator::where('project_id', $p->id)->findOrFail($indicator);
        $this->projects->saveIndicator($p, $request->validateWithBag('indicator', ProjectService::indicatorRules()), $i);

        return redirect()->to(route('engagement.projects.edit', $p->id) . '#resultats')->with('success', 'Résultat mis à jour.');
    }

    public function destroyIndicator(Request $request, int $project, int $indicator)
    {
        $mutual = $this->authorizeMutual($request);
        $p = $this->project($mutual, $project);
        ProjectIndicator::where('project_id', $p->id)->findOrFail($indicator)->delete();

        return redirect()->to(route('engagement.projects.edit', $p->id) . '#resultats')->with('success', 'Résultat supprimé.');
    }

    public function storeUpdate(Request $request, int $project)
    {
        $mutual = $this->authorizeMutual($request);
        $p = $this->project($mutual, $project);
        $data = $request->validateWithBag('progress', [
            'update_title' => ['required', 'string', 'max:180'], 'update_body' => ['required', 'string', 'max:5000'], 'announce' => ['nullable', 'boolean'],
        ]);
        $this->projects->addUpdate($p, $data['update_title'], $data['update_body'], (bool) ($data['announce'] ?? false), $mutual, null, $request->user()->id, $request->user()->name);

        return redirect()->to(route('engagement.projects.edit', $p->id) . '#actualites')->with('success', 'Actualité du projet publiée.');
    }

    // --- Opportunités ---

    public function createOpportunity(Request $request)
    {
        $mutual = $this->authorizeMutual($request);
        $o = new Opportunity(['category' => 'emploi', 'apply_mode' => 'platform', 'status' => 'published',
            'country_id' => $mutual->country_id, 'region' => $mutual->region, 'city' => $mutual->city]);

        return view('tenant.engagement.opportunity-form', $this->opportunityFormData($mutual, $o));
    }

    public function storeOpportunity(Request $request)
    {
        $mutual = $this->authorizeMutual($request);
        $data = $request->validate(ProjectService::opportunityRules(), ProjectService::opportunityMessages());
        $this->projects->saveOpportunity($mutual, $data, null, null, $request->user()->id, $request->user()->name);

        return redirect()->route('engagement.index', ['onglet' => 'opportunites'])->with('success', 'Offre enregistrée.');
    }

    public function editOpportunity(Request $request, int $opportunity)
    {
        $mutual = $this->authorizeMutual($request);

        return view('tenant.engagement.opportunity-form', $this->opportunityFormData($mutual, $this->opportunity($mutual, $opportunity)));
    }

    public function updateOpportunity(Request $request, int $opportunity)
    {
        $mutual = $this->authorizeMutual($request);
        $o = $this->opportunity($mutual, $opportunity);
        $data = $request->validate(ProjectService::opportunityRules(), ProjectService::opportunityMessages());
        $this->projects->saveOpportunity($mutual, $data, $o, null, $request->user()->id, $request->user()->name);

        return redirect()->route('engagement.index', ['onglet' => 'opportunites'])->with('success', 'Offre mise à jour.');
    }

    public function availability(Request $request, int $opportunity)
    {
        $mutual = $this->authorizeMutual($request);
        $data = $request->validate(['availability' => ['required', 'in:' . implode(',', array_keys(Opportunity::AVAILABILITIES))]]);
        $this->projects->setAvailability($this->opportunity($mutual, $opportunity), $data['availability']);

        return back()->with('success', 'Statut de l’offre mis à jour.');
    }

    public function destroyOpportunity(Request $request, int $opportunity)
    {
        $mutual = $this->authorizeMutual($request);
        $this->opportunity($mutual, $opportunity)->delete();

        return redirect()->route('engagement.index', ['onglet' => 'opportunites'])->with('success', 'Offre supprimée.');
    }

    // ---

    /** Demandes visibles par cet utilisateur (candidatures : administrateur seulement). */
    private function visibleRequests(Request $request, Mutual $mutual)
    {
        return SocialRequest::where('mutual_id', $mutual->id)
            ->when(! $request->user()->hasRole('mutual_admin'), fn ($q) => $q->where('kind', '!=', 'application'));
    }

    private function findRequest(Request $request, Mutual $mutual, string $reference): SocialRequest
    {
        return $this->visibleRequests($request, $mutual)->where('reference', $reference)->firstOrFail();
    }

    private function opportunityFormData(Mutual $mutual, Opportunity $o): array
    {
        return [
            'mutual' => $mutual, 'o' => $o,
            'countries' => Country::where('active', true)->orderBy('name')->get(['id', 'name']),
            'pageProjects' => Project::where('mutual_id', $mutual->id)->where('status', '!=', 'hidden')->orderBy('title')->pluck('title', 'id'),
        ];
    }

    private function project(Mutual $mutual, int $id): Project
    {
        return Project::where('mutual_id', $mutual->id)->findOrFail($id);
    }

    private function opportunity(Mutual $mutual, int $id): Opportunity
    {
        return Opportunity::where('mutual_id', $mutual->id)->findOrFail($id);
    }

    private function publicUrl(string $path): string
    {
        return rtrim(config('app.url'), '/') . $path;
    }

    private function authorizeMutual(Request $request): Mutual
    {
        abort_unless($request->user()?->hasAnyRole(self::ROLES), 403);

        return tenant();
    }
}
