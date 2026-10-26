<?php

namespace App\Http\Controllers\Social;

use App\Models\Central\Mutual;
use App\Models\Social\Opportunity;
use App\Models\Social\Project;
use App\Models\Social\SocialRequest;
use App\Services\Social\FeedService;
use App\Services\Social\PageService;
use App\Services\Social\RequestService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Côté membre : « Je m'engage », « Soutenir ou devenir partenaire » et candidatures,
 * puis suivi de ses demandes. Démarche explicite avec consentement à la transmission
 * des coordonnées. Aucune donnée médicale demandée, aucun paiement.
 */
class RequestController extends SocialController
{
    public function __construct(FeedService $feed, private RequestService $requests)
    {
        parent::__construct($feed);
    }

    /** Formulaire : ?type=engagement|support|application & projet=… | offre=… | page=… */
    public function create(Request $request)
    {
        [$kind, $page, $project, $opportunity] = $this->target($request);
        $viewer = $this->viewer();

        $open = SocialRequest::where('public_account_id', $viewer->id)->where('mutual_id', $page->id)->where('kind', $kind)
            ->where('project_id', $project?->id)->where('opportunity_id', $opportunity?->id)
            ->whereIn('status', SocialRequest::OPEN)->first();
        if ($open) {
            return redirect()->route('social.request', $open->reference)->with('info', 'Vous avez déjà une demande en cours sur ce sujet.');
        }

        return view('social.requests.create', compact('kind', 'page', 'project', 'opportunity') + ['account' => $viewer]);
    }

    public function store(Request $request)
    {
        [$kind, $page, $project, $opportunity] = $this->target($request);
        $request->merge(['contact_phone' => AuthController::normalizePhone($request->input('contact_phone'))]);
        $subtypes = match ($kind) {
            'engagement' => array_keys(SocialRequest::ENGAGEMENT_TYPES),
            'support'    => array_keys(SocialRequest::SUPPORT_TYPES),
            default      => [],
        };

        $data = $request->validate([
            'subtype'           => [$subtypes ? 'required' : 'nullable', Rule::in($subtypes ?: ['-'])],
            'message'           => ['required', 'string', 'min:20', 'max:3000'],
            'organisation_name' => ['nullable', 'string', 'max:180'],
            'availability'      => ['nullable', 'string', 'max:180'],
            'contact_name'      => ['required', 'string', 'max:180'],
            'contact_phone'     => ['nullable', 'regex:/^\+?[0-9]{8,15}$/', 'required_without:contact_email'],
            'contact_email'     => ['nullable', 'email:rfc', 'max:180', 'required_without:contact_phone'],
            'cv'                => ['nullable', 'file', 'mimes:pdf', 'max:' . config('mxconnect.social.adhesion_doc_kb', 5120)],
            'consent'           => ['accepted'],
            'no_medical'        => ['accepted'],
        ], [
            'subtype.required'               => 'Choisissez une forme d’engagement ou de soutien.',
            'message.min'                    => 'Présentez votre démarche en quelques phrases (20 caractères minimum).',
            'contact_phone.regex'            => 'Numéro de téléphone invalide.',
            'contact_phone.required_without' => 'Indiquez un téléphone ou un e-mail pour être recontacté(e).',
            'contact_email.required_without' => 'Indiquez un e-mail ou un téléphone pour être recontacté(e).',
            'cv.mimes'                       => 'Le CV doit être un fichier PDF.',
            'cv.max'                         => 'Le CV doit faire moins de ' . round(config('mxconnect.social.adhesion_doc_kb', 5120) / 1024) . ' Mo.',
            'consent.accepted'               => 'Votre accord est nécessaire pour transmettre vos coordonnées à l’organisation.',
            'no_medical.accepted'            => 'Merci de confirmer que vous n’avez indiqué aucune donnée médicale.',
        ]);

        $cv = $kind === 'application' ? $request->file('cv') : null;
        $r = $this->requests->submit($kind, $page, $this->viewer(), collect($data)->except(['cv', 'consent', 'no_medical'])->all(), $project, $opportunity, $cv);

        return redirect()->route('social.request', $r->reference)
            ->with('ok', "Votre demande a été transmise à {$page->name} (référence {$r->reference}). Vous serez notifié(e) de sa réponse.");
    }

    public function index(Request $request)
    {
        $items = SocialRequest::where('public_account_id', $this->viewer()->id)
            ->with(['mutual.profile', 'project', 'opportunity'])->latest('id')->paginate(20);

        return view('social.requests.index', ['items' => $items]);
    }

    public function show(string $reference)
    {
        $r = $this->own($reference)->load(['mutual.profile', 'project', 'opportunity', 'events']);

        return view('social.requests.show', ['r' => $r]);
    }

    public function withdraw(string $reference)
    {
        $r = $this->own($reference);
        $this->requests->withdraw($r);

        return redirect()->route('social.request', $r->reference)->with('ok', 'Votre demande a été retirée.');
    }

    public function cv(string $reference)
    {
        return $this->requests->cvResponse($this->own($reference));
    }

    // ---

    private function own(string $reference): SocialRequest
    {
        $r = SocialRequest::where('reference', $reference)->firstOrFail();
        abort_unless($r->public_account_id === $this->viewer()->id, 404);

        return $r;
    }

    /** @return array{0:string,1:Mutual,2:?Project,3:?Opportunity} */
    private function target(Request $request): array
    {
        $kind = (string) $request->input('type');
        abort_unless(array_key_exists($kind, SocialRequest::KINDS), 404);
        $project = $opportunity = null;

        if ($kind === 'application') {
            $opportunity = Opportunity::where('slug', (string) $request->input('offre'))->with('mutual.profile')->firstOrFail();
            abort_unless($opportunity->isPublic(), 404);
            $page = $opportunity->mutual;
        } elseif ($request->filled('projet')) {
            $project = Project::where('slug', (string) $request->input('projet'))->with('mutual.profile')->firstOrFail();
            abort_unless($project->isPublic(), 404);
            // Bouton affiché seulement si le projet exprime ce besoin : même règle côté serveur.
            abort_unless($kind === 'engagement' ? $project->seeks_volunteers : $project->seeks_partners, 404);
            $page = $project->mutual;
        } else {
            $page = Mutual::where('slug', (string) $request->input('page'))->with('profile')->firstOrFail();
        }
        abort_if($page->status === 'suspended', 404);
        abort_if($page->profile?->is_demo, 403, 'Organisation de démonstration : aucune demande ne peut lui être envoyée.');
        if (PageService::canPublish($this->viewer(), $page)) {
            abort(403, 'Vous gérez cette page : vous ne pouvez pas lui adresser une demande.');
        }

        return [$kind, $page, $project, $opportunity];
    }
}
