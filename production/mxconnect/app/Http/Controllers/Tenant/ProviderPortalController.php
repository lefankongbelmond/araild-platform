<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Concerns\MutualContext;
use App\Models\Central\CareProvider;
use App\Models\Tenant\ActType;
use App\Models\Tenant\CareClaim;
use App\Models\Tenant\Dependent;
use App\Models\Tenant\Member;
use App\Models\Tenant\Prestation;
use App\Models\Tenant\ProviderConvention;
use App\Models\Tenant\ProviderInvoice;
use App\Models\Tenant\RightsCheck;
use App\Services\ClaimCaptureService;
use App\Services\RightsVerificationService;
use App\Services\Tenant\BeneficiaryLookupService;
use App\Services\Tenant\CoverageService;
use App\Services\Tenant\InvoiceDocumentService;
use App\Support\RightsStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Portail des prestataires de soins conventionnés (rôle « provider », matrice des droits).
 *
 *  - Vérifier les droits d'un adhérent (code adhérent ou lien de la carte) : VERDICT seulement,
 *    jamais de donnée médicale ; chaque vérification est journalisée (rights_checks).
 *  - Déclarer une prise en charge, uniquement à partir d'une vérification positive récente
 *    (pas d'accès libre au fichier des adhérents). La mutuelle valide ensuite (« 4 yeux »).
 *  - Suivre SES prises en charge et SES factures : tout est filtré sur son provider_id.
 *  - Demander le paiement (tiers payant) des prestations validées : la mutuelle paie hors
 *    plateforme ; MX-CONNECT ne détient ni ne simule aucun fonds.
 */
class ProviderPortalController extends Controller
{
    use MutualContext;

    /** Une vérification positive permet de déclarer pendant 12 heures. */
    private const CHECK_HOURS = 12;

    public function __construct(private RightsVerificationService $rights, private ClaimCaptureService $capture) {}

    public function index(Request $request)
    {
        [$provider, $convention] = $this->context($request);

        return view('tenant.portal.index', $this->baseData($request, $provider, $convention) + ['result' => null, 'search' => null]);
    }

    public function verify(Request $request, BeneficiaryLookupService $lookup)
    {
        [$provider, $convention] = $this->context($request, 'act');
        $data = $request->validate(['code' => ['required', 'string', 'max:500']], ['code.required' => 'Saisissez le code adhérent ou collez le lien de la carte.']);

        // Lien ou jeton de la carte (QR) : signature vérifiée ; sinon code adhérent imprimé sur la carte.
        $id = $lookup->identify($data['code']);

        return $this->runCheck($request, $provider, $convention, $id['member'], null, $id['method'], $id['invalid_card']);
    }

    /**
     * Recherche encadrée d'un bénéficiaire sans sa carte (2026-11-17) : nom + date de naissance,
     * ou téléphone + année de naissance. Journalisée, 5 résultats au plus, code masqué.
     */
    public function search(Request $request, BeneficiaryLookupService $lookup)
    {
        [$provider, $convention] = $this->context($request, 'act');
        $mode = $request->input('mode') === 'phone' ? 'phone' : 'name';
        $data = $mode === 'name'
            ? $request->validate([
                'last_name' => ['required', 'string', 'min:2', 'max:120'], 'first_name' => ['nullable', 'string', 'max:120'],
                'birth_date' => ['required', 'date', 'before_or_equal:today'],
            ], ['last_name.required' => 'Indiquez le nom de famille.', 'birth_date.required' => 'La date de naissance complète est obligatoire pour une recherche par nom.'])
            : $request->validate([
                'phone' => ['required', 'string', 'max:30', 'regex:/(\D*\d){8,}/'], 'birth_year' => ['required', 'integer', 'min:1900', 'max:' . now()->year],
            ], ['phone.regex' => 'Saisissez le numéro complet (8 chiffres au moins).', 'birth_year.required' => 'L’année de naissance est obligatoire pour une recherche par téléphone.']);
        abort_unless(BeneficiaryLookupService::hasSearchTable(), 503, 'Mise à jour de la mutuelle en cours : réessayez dans un instant.');

        $results = $lookup->search($mode, $data, $request->user(), $provider->id);

        return view('tenant.portal.index', $this->baseData($request, $provider, $convention) + [
            'result' => null, 'search' => ['mode' => $mode, 'results' => isset($results['too_many']) ? [] : $results, 'too_many' => isset($results['too_many'])],
        ]);
    }

    /** Vérifier les droits d'un résultat de recherche (jeton chiffré, propre à la personne connectée). */
    public function pick(Request $request, BeneficiaryLookupService $lookup)
    {
        [$provider, $convention] = $this->context($request, 'act');
        $request->validate(['pick' => ['required', 'string', 'max:2000']]);
        $found = $lookup->pick((string) $request->input('pick'), $request->user(), $provider->id);
        abort_unless($found, 410, 'Ce résultat de recherche a expiré : relancez la recherche.');

        return $this->runCheck($request, $provider, $convention, $found['member'], $found['dependent'], 'search');
    }

    /**
     * Estimation de la prise en charge AVANT les soins (rien n'est enregistré), à partir d'une
     * vérification positive récente : part mutuelle, part patient, part non couverte, plafond.
     */
    public function estimate(Request $request, int $check, CoverageService $coverage)
    {
        [$provider, $convention] = $this->context($request, 'act');
        $c = $this->usableCheck($request, $provider, $check);
        $data = $request->validate(['act_type_id' => ['required', 'integer'], 'total' => ['required', 'string', 'max:20']],
            ['act_type_id.required' => 'Choisissez l’acte.', 'total.required' => 'Indiquez le montant prévu.']);
        abort_unless(ActType::whereKey($data['act_type_id'])->exists(), 422, 'Acte inconnu.');
        $total = $this->currency()->toMinor($this->amount($data['total']));
        if ($total <= 0) {
            return back()->withErrors(['total' => 'Indiquez le montant prévu des soins.']);
        }
        $version = $this->capture->versionFor($c->member, now()->toDateString());
        abort_unless($version, 422, 'Aucune garantie en cours pour cet adhérent.');
        $estimate = $coverage->estimate($version, $c->member, (int) $data['act_type_id'], $total, now()->toDateString());

        return $this->resultView($request, $provider, $convention, $this->verdictFor($c), $estimate);
    }

    public function declare(Request $request, int $check)
    {
        [$provider, $convention] = $this->context($request, 'act');
        $c = $this->usableCheck($request, $provider, $check);

        return view('tenant.portal.declare', [
            'provider' => $provider, 'check' => $c, 'member' => $c->member,
            'dependents' => $c->member->dependents()->where('status', 'active')->orderBy('first_name')->get(),
            'acts' => ActType::orderBy('label')->get(),
            'currency' => $this->currency(),
            'checkedDependent' => RightsCheck::hasIdentificationColumns() ? $c->dependent_id : null,
        ]);
    }

    public function storeDeclaration(Request $request, int $check)
    {
        [$provider, $convention] = $this->context($request, 'act');
        $c = $this->usableCheck($request, $provider, $check);
        $member = $c->member;
        $data = $request->validate([
            'beneficiary' => ['required', 'string'],
            'act_type_id' => ['required', 'integer'],
            'care_date'   => ['required', 'date', 'before_or_equal:today', 'after_or_equal:' . now()->subDays(30)->toDateString()],
            'total'       => ['required', 'string', 'max:20'],
        ], [
            'care_date.before_or_equal' => 'La date des soins ne peut pas être dans le futur.',
            'care_date.after_or_equal'  => 'Les soins de plus de 30 jours se déclarent auprès de la mutuelle.',
        ]);
        abort_unless(ActType::whereKey($data['act_type_id'])->exists(), 422, 'Acte inconnu.');
        $dependentId = null;
        if ($data['beneficiary'] !== 'member') {
            $dependentId = $member->dependents()->where('status', 'active')->whereKey((int) $data['beneficiary'])->value('id');
            abort_unless($dependentId, 422, 'Bénéficiaire inconnu.');
        }
        $total = $this->amount($data['total']);
        if ($total <= 0) {
            return back()->withInput()->withErrors(['total' => 'Indiquez le montant total des soins.']);
        }

        // Double saisie probable (2026-11-15) : même établissement, même personne, même acte, même
        // date, déjà déclarée et non rejetée => confirmation explicite exigée. Ce n'est PAS un refus :
        // deux actes identiques le même jour peuvent être réels ; le contrôle anti-fraude de la
        // mutuelle reçoit de toute façon un signal à examiner. Une prise en charge faite par un AUTRE
        // établissement n'est jamais révélée ici (confidentialité du parcours de soins).
        $twin = Prestation::where('provider_id', $provider->id)->where('act_type_id', (int) $data['act_type_id'])
            ->whereDate('care_date', $data['care_date'])->where('status', '!=', 'rejected')
            ->whereHas('careClaim', fn ($q) => $q->where('member_id', $member->id)
                ->when($dependentId, fn ($w) => $w->where('dependent_id', $dependentId), fn ($w) => $w->whereNull('dependent_id')))
            ->latest('id')->first();
        if ($twin && ! $request->boolean('confirm_distinct')) {
            return back()->withInput()->with('duplicate_of', $twin->id);
        }

        // Recours + prestation créés ensemble ; droits refusés => rien n'est enregistré.
        $reasons = [];
        try {
            $p = DB::transaction(function () use ($member, $dependentId, $data, $provider, $request, $total, &$reasons) {
                $claim = CareClaim::create(['member_id' => $member->id, 'dependent_id' => $dependentId, 'opened_on' => $data['care_date'], 'status' => 'open']);
                $result = $this->capture->capture($claim, [
                    'act_type_id' => (int) $data['act_type_id'], 'provider_id' => $provider->id,
                    'care_date'   => $data['care_date'], 'total_minor' => $this->currency()->toMinor($total),
                ], $request->user(), 'provider');
                if (! $result['ok']) {
                    $reasons = $result['reasons'];
                    throw new \RuntimeException('rights_refused');
                }

                return $result['prestation'];
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== 'rights_refused') {
                throw $e;
            }

            return back()->withInput()->with('rights_block', $reasons);
        }

        return redirect()->route('portal.claims')->with('success', sprintf(
            'Prise en charge n° %d déclarée. Part mutuelle : %s ; à régler par le patient : %s. Elle sera validée par la mutuelle.',
            $p->id, $this->currency()->format($p->mutual_part_minor), $this->currency()->format($p->beneficiary_part_minor)));
    }

    public function claims(Request $request)
    {
        [$provider, $convention] = $this->context($request);
        $status = in_array($request->get('statut'), ['captured', 'validated', 'rejected'], true) ? $request->get('statut') : null;

        return view('tenant.portal.claims', [
            'provider' => $provider, 'convention' => $convention, 'status' => $status, 'currency' => $this->currency(),
            'items' => Prestation::with(['careClaim.member', 'careClaim.dependent', 'actType'])->where('provider_id', $provider->id)
                ->when($status, fn ($q) => $q->where('status', $status))->latest('id')->paginate(25)->withQueryString(),
            'counts' => Prestation::where('provider_id', $provider->id)->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function invoices(Request $request)
    {
        [$provider, $convention] = $this->context($request);
        $invoiced = ProviderInvoice::where('provider_id', $provider->id)->whereNotNull('care_claim_id')->pluck('care_claim_id');

        return view('tenant.portal.invoices', [
            'provider' => $provider, 'convention' => $convention, 'currency' => $this->currency(),
            'invoices' => ProviderInvoice::with('careClaim.member')->where('provider_id', $provider->id)->latest('id')->paginate(25),
            // Recours avec des prestations VALIDÉES de ce prestataire, pas encore facturés par lui.
            'billable' => CareClaim::with('member')->whereNotIn('id', $invoiced)
                ->whereHas('prestations', fn ($q) => $q->where('provider_id', $provider->id)->where('status', 'validated'))
                ->withSum(['prestations as due_minor' => fn ($q) => $q->where('provider_id', $provider->id)->where('status', 'validated')], 'mutual_part_minor')
                ->latest('id')->limit(50)->get(),
        ]);
    }

    public function requestPayment(Request $request, int $claim, InvoiceDocumentService $docs)
    {
        [$provider, $convention] = $this->context($request, 'bill');
        $data = $request->validate(['provider_reference' => ['nullable', 'string', 'max:60']] + InvoiceDocumentService::rules(), InvoiceDocumentService::messages());
        $c = CareClaim::findOrFail($claim);

        // Facture et pièce ensemble : un PDF refusé n'enregistre aucune demande. Le recours est
        // verrouillé pendant la transaction (2026-11-15) : un double clic ou deux postes en même
        // temps ne peuvent pas créer deux demandes de paiement pour les mêmes soins.
        $amount = DB::transaction(function () use ($provider, $c, $request, $data, $docs) {
            CareClaim::whereKey($c->id)->lockForUpdate()->first();
            abort_if(ProviderInvoice::where('provider_id', $provider->id)->where('care_claim_id', $c->id)->exists(), 422, 'Cette prise en charge est déjà facturée.');
            $amount = (int) $c->prestations()->where('provider_id', $provider->id)->where('status', 'validated')->sum('mutual_part_minor');
            abort_if($amount <= 0, 422, 'Aucune prestation validée à facturer.');
            $invoice = ProviderInvoice::create([
                'provider_id' => $provider->id, 'care_claim_id' => $c->id, 'amount_minor' => $amount, 'status' => 'captured',
                'captured_by' => $request->user()->id, 'provider_reference' => $data['provider_reference'] ?? null,
            ]);
            if ($request->hasFile('document')) {
                $docs->attach($invoice, $request->file('document'));
            }

            return $amount;
        });

        return back()->with('success', 'Demande de paiement transmise à la mutuelle (' . $this->currency()->format($amount) . '). Le paiement est fait par la mutuelle, hors de la plateforme.');
    }

    /** Joindre (ou remplacer) la facture PDF tant que la mutuelle n'a pas payé. */
    public function attachDocument(Request $request, int $invoice, InvoiceDocumentService $docs)
    {
        [$provider, $convention] = $this->context($request, 'bill');
        $inv = ProviderInvoice::where('provider_id', $provider->id)->findOrFail($invoice);
        abort_unless(in_array($inv->status, ['captured', 'processed'], true), 422, 'Cette facture est déjà en paiement : la pièce ne se modifie plus.');
        $request->validate(['document' => ['required', ...InvoiceDocumentService::rules()['document']]], InvoiceDocumentService::messages() + ['document.required' => 'Choisissez le fichier PDF de la facture.']);
        $docs->attach($inv, $request->file('document'));

        return back()->with('success', "Facture PDF jointe à la demande n° {$inv->id}.");
    }

    /** Le prestataire retélécharge la pièce de SA facture. */
    public function document(Request $request, int $invoice, InvoiceDocumentService $docs)
    {
        [$provider] = $this->context($request);

        return $docs->download(ProviderInvoice::where('provider_id', $provider->id)->findOrFail($invoice));
    }

    // ---

    /**
     * Prestataire représenté par le compte connecté et sa convention.
     *  - 'read' : consultation de son historique (toujours possible) ;
     *  - 'act'  : vérifier, déclarer => convention EN VIGUEUR et établissement actif ;
     *  - 'bill' : facturer des soins DÉJÀ validés => convention non désactivée par la mutuelle et
     *             établissement actif, même si sa date de fin est passée (2026-11-15 : des soins
     *             faits pendant la convention restent facturables après son échéance).
     * Les vues reçoivent $portal = ['act' => bool, 'bill' => bool] (aucun bouton inutilisable).
     *
     * @return array{0:CareProvider,1:ProviderConvention}
     */
    private function context(Request $request, string $mode = 'read'): array
    {
        $user = $request->user();
        $provider = $user->care_provider_id ? CareProvider::on('central')->find($user->care_provider_id) : null;
        $convention = ProviderConvention::forProvider($provider?->id);
        abort_unless($provider && $convention, 403, 'Ce compte n’est rattaché à aucun prestataire conventionné. Contactez la mutuelle.');

        $canAct = $provider->active !== false && $convention->inForce();
        $canBill = $provider->active !== false && (bool) $convention->active;
        // Compte ouvert depuis l'espace prestataire central (2026-11-16) : les boutons suivent aussi
        // les droits de la personne dans son établissement (la route est déjà contrôlée par TenantAccess).
        $state = [
            'act'     => $canAct,
            'bill'    => $canBill && \App\Support\TenantAccess::can('portal.invoices.store', $user),
            'declare' => \App\Support\TenantAccess::can('portal.declare', $user),
            'space'   => $user->provider_account_id ? rtrim((string) config('app.url'), '/') . '/espace-prestataire' : null,
        ];
        view()->share('portal', $state);
        abort_if($mode === 'act' && ! $canAct, 403, 'La convention de votre établissement avec la mutuelle n’est pas en vigueur. Contactez la mutuelle.');
        abort_if($mode === 'bill' && ! $canBill, 403, 'La convention de votre établissement a été suspendue par la mutuelle : la facturation en ligne est fermée. Contactez la mutuelle.');

        return [$provider, $convention];
    }

    /** Vérification positive, faite par CE compte pour CE prestataire, il y a moins de 12 heures. */
    private function usableCheck(Request $request, CareProvider $provider, int $check): RightsCheck
    {
        $c = RightsCheck::with('member')->whereKey($check)->where('user_id', $request->user()->id)
            ->where('care_provider_id', $provider->id)->where('ok', true)->whereNotNull('member_id')->first();
        abort_unless($c, 404);
        abort_if($c->created_at->lt(now()->subHours(self::CHECK_HOURS)), 410, 'Cette vérification a expiré : vérifiez de nouveau les droits de l’adhérent.');

        return $c;
    }

    /** Données communes de la page « Vérifier les droits ». */
    private function baseData(Request $request, CareProvider $provider, ProviderConvention $convention): array
    {
        return [
            'provider' => $provider, 'convention' => $convention, 'currency' => $this->currency(),
            'recent'   => RightsCheck::with(RightsCheck::hasIdentificationColumns() ? ['member', 'dependent'] : ['member'])
                ->where('care_provider_id', $provider->id)->where('user_id', $request->user()->id)->latest('id')->limit(8)->get(),
            // Code transmis depuis l'espace prestataire central (recherche par code ou QR), à confirmer d'un clic.
            'prefill'  => $request->session()->get('portal_prefill'),
        ];
    }

    /** Vérifie les droits d'un bénéficiaire identifié (ou non) et journalise la vérification. */
    private function runCheck(Request $request, CareProvider $provider, ProviderConvention $convention, ?Member $member, ?Dependent $dependent, string $method, bool $invalidCard = false)
    {
        $extra = RightsCheck::hasIdentificationColumns() ? ['dependent_id' => $dependent?->id, 'method' => $method] : [];
        if (! $member) {
            $reasons = [$invalidCard ? 'invalid_card' : 'unknown_member'];
            $check = RightsCheck::create(['user_id' => $request->user()->id, 'care_provider_id' => $provider->id, 'member_id' => null,
                'ok' => false, 'reasons' => $reasons, 'created_at' => now()] + $extra);

            return $this->resultView($request, $provider, $convention, ['ok' => false, 'reasons' => $reasons, 'member' => null, 'dependent' => null, 'check' => $check]);
        }

        $version = $this->capture->versionFor($member, now()->toDateString());
        $verdict = $version
            ? $this->rights->verify($member, $version->guarantee_id, now()->toDateString(), null, $provider->id)
            : ['ok' => false, 'reasons' => ['no_active_guarantee']];
        // Un ayant droit retiré de l'adhésion n'a plus de droits, même si l'adhérent en a.
        if ($dependent && $dependent->status !== 'active') {
            $verdict = ['ok' => false, 'reasons' => array_values(array_unique([...$verdict['reasons'], 'dependent_removed']))];
        }

        $check = RightsCheck::create(['user_id' => $request->user()->id, 'care_provider_id' => $provider->id, 'member_id' => $member->id,
            'ok' => $verdict['ok'], 'reasons' => $verdict['reasons'], 'created_at' => now()] + $extra);
        $request->session()->forget('portal_prefill');

        return $this->resultView($request, $provider, $convention, $verdict + ['member' => $member, 'dependent' => $dependent, 'check' => $check]);
    }

    /** Verdict d'une vérification déjà journalisée (pour réafficher la fiche avec une estimation). */
    private function verdictFor(RightsCheck $c): array
    {
        return ['ok' => (bool) $c->ok, 'reasons' => (array) $c->reasons, 'member' => $c->member,
            'dependent' => RightsCheck::hasIdentificationColumns() && $c->dependent_id ? Dependent::find($c->dependent_id) : null, 'check' => $c];
    }

    private function resultView(Request $request, CareProvider $provider, ProviderConvention $convention, array $result, ?array $estimate = null)
    {
        $coverage = app(CoverageService::class);
        $member = $result['member'] ?? null;
        $dependent = $result['dependent'] ?? null;
        $situation = $member ? $coverage->situation($member, $dependent, $provider->id) : null;
        $result['status'] = RightsStatus::of($result['reasons'], $member, $dependent, (bool) ($situation['pending_subscription'] ?? false));
        $result['situation'] = $situation;
        $result['last'] = $member ? $coverage->lastCheck($result['check']) : null;
        // Historique : seulement les prises en charge de CET établissement, et seulement si le rôle le permet.
        $result['history'] = $member && \App\Support\TenantAccess::can('portal.claims', $request->user()) ? $coverage->history($member, $dependent, $provider->id) : null;

        return view('tenant.portal.index', $this->baseData($request, $provider, $convention) + [
            'result' => $result, 'search' => null, 'estimate' => $estimate,
            'acts' => $result['ok'] ? ActType::orderBy('label')->get(['id', 'label']) : collect(),
        ]);
    }
}
