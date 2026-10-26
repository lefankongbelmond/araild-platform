<?php

namespace App\Http\Controllers\Social;

use App\Models\Central\Country;
use App\Models\Central\Mutual;
use App\Models\Social\Opportunity;
use App\Models\Social\Project;
use App\Models\Social\ProjectIndicator;
use App\Models\Social\ProjectUpdate;
use App\Models\Social\SocialRequest;
use App\Services\Social\FeedService;
use App\Services\Social\PageService;
use App\Services\Social\ProjectService;
use App\Services\Social\RequestService;
use Illuminate\Http\Request;

/**
 * Espace de gestion d'une page sur le réseau (mutuelle ou page créée par un membre) :
 * projets et résultats, emplois et opportunités, demandes reçues.
 *  - publier / modifier : tout gestionnaire de la page (propriétaire, administrateur, éditeur) ;
 *  - lire et traiter les demandes (données personnelles) : propriétaire et administrateurs seulement.
 */
class PageManageController extends SocialController
{
    public function __construct(FeedService $feed, private ProjectService $projects, private RequestService $requests)
    {
        parent::__construct($feed);
    }

    // --- Projets ---

    public function projects(string $slug)
    {
        $page = $this->page($slug);

        return view('social.manage.projects', $this->common($page, 'projets') + [
            'items' => Project::where('mutual_id', $page->id)->withCount('indicators')->latest('id')->paginate(20),
        ]);
    }

    public function createProject(string $slug)
    {
        $page = $this->page($slug);

        return view('social.manage.project-form', $this->common($page, 'projets') + ['project' => new Project(['phase' => 'ongoing', 'status' => 'published'])]);
    }

    public function storeProject(Request $request, string $slug)
    {
        $page = $this->page($slug);
        $data = $request->validate(ProjectService::projectRules(), ProjectService::projectMessages());
        $project = $this->projects->saveProject($page, $data, $request->file('cover'), null, $this->viewer());

        return redirect()->route('social.manage.project.edit', [$page->slug, $project->id])
            ->with('ok', 'Projet enregistré. Ajoutez maintenant ses résultats chiffrés et ses actualités.');
    }

    public function editProject(string $slug, int $project)
    {
        $page = $this->page($slug);
        $p = $this->project($page, $project)->load(['indicators', 'updates']);

        return view('social.manage.project-form', $this->common($page, 'projets') + ['project' => $p]);
    }

    public function updateProject(Request $request, string $slug, int $project)
    {
        $page = $this->page($slug);
        $p = $this->project($page, $project);
        $data = $request->validate(ProjectService::projectRules(), ProjectService::projectMessages());
        $this->projects->saveProject($page, $data, $request->file('cover'), $p, $this->viewer());

        return redirect()->route('social.manage.project.edit', [$page->slug, $p->id])->with('ok', 'Projet mis à jour.');
    }

    public function destroyProject(string $slug, int $project)
    {
        $page = $this->page($slug);
        $this->projects->deleteProject($this->project($page, $project));

        return redirect()->route('social.manage.projects', $page->slug)->with('ok', 'Projet supprimé.');
    }

    public function storeIndicator(Request $request, string $slug, int $project)
    {
        $page = $this->page($slug);
        $p = $this->project($page, $project);
        $this->projects->saveIndicator($p, $request->validateWithBag('indicator', ProjectService::indicatorRules()));

        return redirect()->to(route('social.manage.project.edit', [$page->slug, $p->id]) . '#resultats')->with('ok', 'Résultat ajouté (déclaré par votre organisation).');
    }

    public function updateIndicator(Request $request, string $slug, int $project, int $indicator)
    {
        $page = $this->page($slug);
        $p = $this->project($page, $project);
        $i = ProjectIndicator::where('project_id', $p->id)->findOrFail($indicator);
        $wasVerified = $i->isVerified();
        $i = $this->projects->saveIndicator($p, $request->validateWithBag('indicator', ProjectService::indicatorRules()), $i);

        return redirect()->to(route('social.manage.project.edit', [$page->slug, $p->id]) . '#resultats')
            ->with('ok', $wasVerified && ! $i->isVerified() ? 'Résultat modifié : il redevient « déclaré » jusqu’à une nouvelle vérification.' : 'Résultat mis à jour.');
    }

    public function destroyIndicator(string $slug, int $project, int $indicator)
    {
        $page = $this->page($slug);
        $p = $this->project($page, $project);
        ProjectIndicator::where('project_id', $p->id)->findOrFail($indicator)->delete();

        return redirect()->to(route('social.manage.project.edit', [$page->slug, $p->id]) . '#resultats')->with('ok', 'Résultat supprimé.');
    }

    public function storeUpdate(Request $request, string $slug, int $project)
    {
        $page = $this->page($slug);
        $p = $this->project($page, $project);
        $data = $request->validateWithBag('progress', [
            'update_title' => ['required', 'string', 'max:180'], 'update_body' => ['required', 'string', 'max:5000'], 'announce' => ['nullable', 'boolean'],
        ], ['update_title.required' => 'Donnez un titre à l’actualité.', 'update_body.required' => 'Écrivez le texte de l’actualité.']);
        $this->projects->addUpdate($p, $data['update_title'], $data['update_body'], (bool) ($data['announce'] ?? false), $page, $this->viewer());

        return redirect()->to(route('social.manage.project.edit', [$page->slug, $p->id]) . '#actualites')->with('ok', 'Actualité du projet publiée.');
    }

    public function destroyUpdate(string $slug, int $project, int $update)
    {
        $page = $this->page($slug);
        $p = $this->project($page, $project);
        ProjectUpdate::where('project_id', $p->id)->findOrFail($update)->delete();

        return redirect()->to(route('social.manage.project.edit', [$page->slug, $p->id]) . '#actualites')->with('ok', 'Actualité supprimée.');
    }

    // --- Opportunités ---

    public function opportunities(string $slug)
    {
        $page = $this->page($slug);

        return view('social.manage.opportunities', $this->common($page, 'opportunites') + [
            'items' => Opportunity::where('mutual_id', $page->id)->latest('id')->paginate(20),
        ]);
    }

    public function createOpportunity(Request $request, string $slug)
    {
        $page = $this->page($slug);
        $o = new Opportunity([
            'category' => array_key_exists((string) $request->get('categorie'), Opportunity::CATEGORIES) ? $request->get('categorie') : 'emploi',
            'apply_mode' => 'platform', 'status' => 'published', 'country_id' => $page->country_id,
            'region' => $page->region, 'city' => $page->city,
            'project_id' => Project::where('mutual_id', $page->id)->whereKey((int) $request->get('projet'))->value('id'),
        ]);

        return view('social.manage.opportunity-form', $this->common($page, 'opportunites') + $this->opportunityFormData($page, $o));
    }

    public function storeOpportunity(Request $request, string $slug)
    {
        $page = $this->page($slug);
        $data = $request->validate(ProjectService::opportunityRules(), ProjectService::opportunityMessages());
        $o = $this->projects->saveOpportunity($page, $data, null, $this->viewer());

        return redirect()->route('social.manage.opportunities', $page->slug)
            ->with('ok', $o->status === 'published' ? 'Opportunité publiée : elle apparaît dans « Emplois et opportunités ».' : 'Brouillon enregistré.');
    }

    public function editOpportunity(string $slug, int $opportunity)
    {
        $page = $this->page($slug);

        return view('social.manage.opportunity-form', $this->common($page, 'opportunites') + $this->opportunityFormData($page, $this->opportunity($page, $opportunity)));
    }

    public function updateOpportunity(Request $request, string $slug, int $opportunity)
    {
        $page = $this->page($slug);
        $o = $this->opportunity($page, $opportunity);
        $data = $request->validate(ProjectService::opportunityRules(), ProjectService::opportunityMessages());
        $this->projects->saveOpportunity($page, $data, $o, $this->viewer());

        return redirect()->route('social.manage.opportunities', $page->slug)->with('ok', 'Opportunité mise à jour.');
    }

    public function availability(Request $request, string $slug, int $opportunity)
    {
        $page = $this->page($slug);
        $o = $this->opportunity($page, $opportunity);
        $data = $request->validate(['availability' => ['required', 'in:' . implode(',', array_keys(Opportunity::AVAILABILITIES))]]);
        $this->projects->setAvailability($o, $data['availability']);

        return back()->with('ok', 'Statut de l’offre : ' . mb_strtolower(Opportunity::AVAILABILITIES[$data['availability']]) . '.');
    }

    public function destroyOpportunity(string $slug, int $opportunity)
    {
        $page = $this->page($slug);
        $this->opportunity($page, $opportunity)->delete();

        return redirect()->route('social.manage.opportunities', $page->slug)->with('ok', 'Opportunité supprimée.');
    }

    // --- Demandes reçues (propriétaire et administrateurs) ---

    public function requests(Request $request, string $slug)
    {
        $page = $this->page($slug, inbox: true);
        $kind = array_key_exists((string) $request->get('type'), SocialRequest::KINDS) ? $request->get('type') : null;
        $status = array_key_exists((string) $request->get('statut'), SocialRequest::STATUSES) ? $request->get('statut') : null;

        return view('social.manage.requests', $this->common($page, 'demandes') + [
            'items' => SocialRequest::where('mutual_id', $page->id)->with(['project', 'opportunity'])
                ->when($kind, fn ($q) => $q->where('kind', $kind))
                ->when($status, fn ($q) => $q->where('status', $status), fn ($q) => $q->whereIn('status', [...SocialRequest::OPEN, 'accepted']))
                ->latest('id')->paginate(25)->withQueryString(),
            'kind' => $kind, 'status' => $status,
        ]);
    }

    public function showRequest(string $slug, string $reference)
    {
        $page = $this->page($slug, inbox: true);
        $r = $this->request($page, $reference)->load(['project', 'opportunity', 'events', 'account']);

        return view('social.manage.request', $this->common($page, 'demandes') + ['r' => $r, 'transitions' => RequestService::TRANSITIONS[$r->status] ?? []]);
    }

    public function decide(Request $request, string $slug, string $reference)
    {
        $page = $this->page($slug, inbox: true);
        $r = $this->request($page, $reference);
        $data = $request->validate(['status' => ['required', 'in:in_review,accepted,rejected,closed'], 'note' => ['nullable', 'string', 'max:2000']]);
        $this->requests->decide($r, $data['status'], $data['note'] ?? null, $this->viewer()->name . ' (' . $page->name . ')');

        return back()->with('ok', 'Demande mise à jour : ' . SocialRequest::STATUSES[$data['status']] . '.');
    }

    public function requestCv(string $slug, string $reference)
    {
        $page = $this->page($slug, inbox: true);

        return $this->requests->cvResponse($this->request($page, $reference));
    }

    // ---

    private function page(string $slug, bool $inbox = false): Mutual
    {
        $page = Mutual::where('slug', $slug)->with(['profile', 'country'])->firstOrFail();
        $viewer = $this->viewer();
        abort_unless(PageService::canPublish($viewer, $page), 403, 'Vous ne gérez pas cette page.');
        if ($inbox) {
            abort_unless(PageService::canAdminister($viewer, $page), 403, 'Seuls le créateur et les administrateurs de la page lisent les demandes reçues.');
        }

        return $page;
    }

    private function common(Mutual $page, string $tab): array
    {
        $canInbox = PageService::canAdminister($this->viewer(), $page);

        return [
            'page' => $page, 'tab' => $tab, 'canInbox' => $canInbox,
            'openRequests' => $canInbox ? SocialRequest::where('mutual_id', $page->id)->where('status', 'submitted')->count() : 0,
        ];
    }

    private function opportunityFormData(Mutual $page, Opportunity $o): array
    {
        return [
            'o' => $o,
            'countries' => Country::where('active', true)->orderBy('name')->get(['id', 'name']),
            'pageProjects' => Project::where('mutual_id', $page->id)->where('status', '!=', 'hidden')->orderBy('title')->pluck('title', 'id'),
        ];
    }

    private function project(Mutual $page, int $id): Project
    {
        return Project::where('mutual_id', $page->id)->findOrFail($id);
    }

    private function opportunity(Mutual $page, int $id): Opportunity
    {
        return Opportunity::where('mutual_id', $page->id)->findOrFail($id);
    }

    private function request(Mutual $page, string $reference): SocialRequest
    {
        return SocialRequest::where('mutual_id', $page->id)->where('reference', $reference)->firstOrFail();
    }
}
