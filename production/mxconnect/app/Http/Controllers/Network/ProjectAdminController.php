<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Models\Social\Opportunity;
use App\Models\Social\Project;
use App\Models\Social\ProjectIndicator;
use App\Services\Social\ProjectService;
use Illuminate\Http\Request;

/**
 * Back-office du réseau : vérification des résultats déclarés par les pages
 * (« Déclaré » → « Vérifié », sur pièces, hors plateforme) et modération des
 * projets et des offres (masquer / réafficher). Rôles super_admin et moderator.
 */
class ProjectAdminController extends Controller
{
    private const ROLES = ['super_admin', 'moderator'];
    public const TABS = ['resultats' => 'Résultats à vérifier', 'verifies' => 'Résultats vérifiés', 'projets' => 'Projets', 'offres' => 'Offres'];

    public function __construct(private ProjectService $projects) {}

    public function index(Request $request)
    {
        $this->authorizeRole($request);
        $tab = array_key_exists((string) $request->get('onglet'), self::TABS) ? $request->get('onglet') : 'resultats';

        $items = match ($tab) {
            'resultats', 'verifies' => ProjectIndicator::query()
                ->when($tab === 'resultats', fn ($q) => $q->whereNull('verified_at'), fn ($q) => $q->whereNotNull('verified_at'))
                ->whereHas('project', fn ($p) => $p->where('status', 'published'))
                ->with('project.mutual')->latest('updated_at')->paginate(30)->withQueryString(),
            'projets' => Project::with('mutual')->latest('id')->paginate(30)->withQueryString(),
            default   => Opportunity::with('mutual')->latest('id')->paginate(30)->withQueryString(),
        };

        return view('network.social.projects', ['tab' => $tab, 'items' => $items]);
    }

    public function verify(Request $request, ProjectIndicator $indicator)
    {
        $this->authorizeRole($request);
        $data = $request->validate(['action' => ['required', 'in:verifier,annuler'], 'note' => ['nullable', 'string', 'max:255']]);
        if ($data['action'] === 'verifier') {
            if (blank($data['note'] ?? null)) {
                return back()->withErrors(['note' => 'Indiquez sur quelle base le chiffre a été vérifié (pièce, rapport, visite…).']);
            }
            $this->projects->verifyIndicator($indicator, $request->user(), $data['note']);

            return back()->with('success', "Résultat « {$indicator->label} » marqué vérifié.");
        }
        $this->projects->unverifyIndicator($indicator);

        return back()->with('success', "Vérification retirée : « {$indicator->label} » redevient déclaré.");
    }

    public function toggleProject(Request $request, int $project)
    {
        $this->authorizeRole($request);
        $p = Project::findOrFail($project);
        abort_if($p->status === 'draft', 422, 'Brouillon : rien à modérer.');
        $p->update(['status' => $p->status === 'hidden' ? 'published' : 'hidden']);

        return back()->with('success', $p->status === 'hidden' ? "Projet « {$p->title} » masqué." : "Projet « {$p->title} » réaffiché.");
    }

    public function toggleOpportunity(Request $request, int $opportunity)
    {
        $this->authorizeRole($request);
        $o = Opportunity::findOrFail($opportunity);
        abort_if($o->status === 'draft', 422, 'Brouillon : rien à modérer.');
        $o->update(['status' => $o->status === 'hidden' ? 'published' : 'hidden']);

        return back()->with('success', $o->status === 'hidden' ? "Offre « {$o->title} » masquée." : "Offre « {$o->title} » réaffichée.");
    }

    private function authorizeRole(Request $request): void
    {
        abort_unless($request->user()?->hasAnyRole(self::ROLES), 403);
    }
}
