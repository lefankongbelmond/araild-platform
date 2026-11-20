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
use App\Services\Tenant\ProviderBillingService;
use App\Services\AccountingRefusal;
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
        $data = $request->validate(['code' => ['required', 'string', 'max:500']], ['code.required' => __('Saisissez le code adhérent ou collez le lien de la carte.')]);

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
            ], ['last_name.required' => __('Indiquez le nom de famille.'), 'birth_date.required' => __('La date de naissance complète est obligatoire pour une recherche par nom.')])
            : $request->validate([
                'phone' => ['required', 'string', 'max:30', 'regex:/(\D*\d){8,}/'], 'birth_year' => ['required', 'integer', 'min:1900', 'max:' . now()->year],
            ], ['phone.regex' => __('Saisissez le numéro complet (8 chiffres au moins).'), 'birth_year.required' => __('L’année de naissance est obligatoire pour une recherche par téléphone.')]);
        abort_unless(BeneficiaryLookupService::hasSearchTable(), 503, __('Mise à jour de la mutuelle en cours : réessayez dans un instant.'));

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
        abort_unless($found, 410, __('Ce résultat de recherche a expiré : relancez la recherche.'));

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
            ['act_type_id.required' => __('Choisissez l’acte.'), 'total.required' => __('Indiquez le montant prévu.')]);
        abort_unless(ActType::whereKey($data['act_type_id'])->exists(), 422, __('Acte inconnu.'));
        $total = $this->currency()->toMinor($this->amount($data['total']));
        if ($total <= 0) {
            return back()->withErrors(['total' => __('Indiquez le montant prévu des soins.')]);
        }
        $version = $this->capture->versionFor($c->member, now()->toDateString());
        abort_unless($version, 422, __('Aucune garantie en cours pour cet adhérent.'));
        $dependent = RightsCheck::hasIdentificationColumns() && $c->dependent_id ? Dependent::find($c->dependent_id) : null;
        $estimate = $coverage->estimate($version, $c->member, (int) $data['act_type_id'], $total, now()->toDateString(), $dependent, $provider->id,
            ['emergency' => $request->boolean('emergency'), 'accident' => $request->boolean('accident')]);

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
            'care_date.before_or_equal' => __('La date des soins ne peut pas être dans le futur.'),
            'care_date.after_or_equal'  => __('Les soins de plus de 30 jours se déclarent auprès de la mutuelle.'),
        ]);
        abort_unless(ActType::whereKey($data['act_type_id'])->exists(), 422, __('Acte inconnu.'));
        $dependentId = null;
        if ($data['beneficiary'] !== 'member') {
            $dependentId = $member->dependents()->where('status', 'active')->whereKey((int) $data['beneficiary'])->value('id');
            abort_unless($dependentId, 422, __('Bénéficiaire inconnu.'));
        }
        $total = $this->amount($data['total']);
        if ($total <= 0) {
            return back()->withInput()->withErrors(['total' => __('Indiquez le montant total des soins.')]);
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
                    'emergency'   => $request->boolean('emergency'), 'accident' => $request->boolean('accident'),
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

    // ------------------------------------------------------------------ accords préalables (2026-11-19)

    /** Mes demandes d'accord préalable (cet établissement seulement) et leurs décisions. */
    public function preauths(Request $request)
    {
        [$provider, $convention] = $this->context($request);

        return view('tenant.portal.preauths', [
            'provider' => $provider, 'convention' => $convention, 'currency' => $this->currency(),
            'items' => \App\Models\Tenant\Preauthorization::with(['member', 'dependent', 'actType'])->where('provider_id', $provider->id)
                ->latest('id')->paginate(25),
        ]);
    }

    public function preauthCreate(Request $request, int $check)
    {
        [$provider, $convention] = $this->context($request, 'act');
        $c = $this->usableCheck($request, $provider, $check);

        return view('tenant.portal.preauth-create', [
            'provider' => $provider, 'check' => $c, 'member' => $c->member, 'currency' => $this->currency(),
            'dependents' => $c->member->dependents()->where('status', 'active')->orderBy('first_name')->get(),
            'acts' => ActType::orderBy('label')->get(),
            'checkedDependent' => RightsCheck::hasIdentificationColumns() ? $c->dependent_id : null,
        ]);
    }

    public function preauthStore(Request $request, int $check, \App\Services\Tenant\PreauthorizationService $service)
    {
        [$provider, $convention] = $this->context($request, 'act');
        $c = $this->usableCheck($request, $provider, $check);
        $member = $c->member;
        $data = $request->validate([
            'beneficiary'   => ['required', 'string'],
            'act_type_id'   => ['required', 'integer'],
            'planned_on'    => ['required', 'date', 'after_or_equal:today', 'before_or_equal:' . now()->addYear()->toDateString()],
            'total'         => ['required', 'string', 'max:20'],
            'clinical_info' => ['nullable', 'string', 'max:2000'],
        ], ['planned_on.after_or_equal' => __('La date prévue ne peut pas être passée. Une urgence se déclare directement (régularisation ensuite).')]);
        abort_unless(ActType::whereKey($data['act_type_id'])->exists(), 422, __('Acte inconnu.'));
        $dependent = null;
        if ($data['beneficiary'] !== 'member') {
            $dependent = $member->dependents()->where('status', 'active')->whereKey((int) $data['beneficiary'])->first();
            abort_unless($dependent, 422, __('Bénéficiaire inconnu.'));
        }
        $total = $this->amount($data['total']);
        if ($total <= 0) {
            return back()->withInput()->withErrors(['total' => __('Indiquez le montant prévu des soins.')]);
        }
        $p = $service->request([
            'member' => $member, 'dependent' => $dependent, 'provider_id' => $provider->id, 'act_type_id' => (int) $data['act_type_id'],
            'planned_on' => $data['planned_on'], 'estimated_minor' => $this->currency()->toMinor($total), 'clinical_info' => $data['clinical_info'] ?? null,
        ], $request->user(), 'provider');

        return redirect()->route('portal.preauth')->with('success', __('Demande d’accord préalable n° :id envoyée à la mutuelle. Vous verrez sa décision ici.', ['id' => $p->id]));
    }

    public function preauthCancel(Request $request, int $preauth, \App\Services\Tenant\PreauthorizationService $service)
    {
        [$provider, $convention] = $this->context($request, 'act');
        $p = \App\Models\Tenant\Preauthorization::whereKey($preauth)->where('provider_id', $provider->id)->firstOrFail();
        try {
            $service->cancel($p, $request->user());
        } catch (\App\Services\AccountingRefusal $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Demande retirée.'));
    }

    // ------------------------------------------------------------------ pharmacie (2026-11-19)

    /** Délivrance d'une ordonnance : n° d'ordonnance, prescripteur, médicaments délivrés. */
    public function pharmacy(Request $request, int $check)
    {
        [$provider, $convention] = $this->context($request, 'act');
        $c = $this->usableCheck($request, $provider, $check);

        return view('tenant.portal.pharmacy', [
            'provider' => $provider, 'check' => $c, 'member' => $c->member, 'currency' => $this->currency(),
            'dependents' => $c->member->dependents()->where('status', 'active')->orderBy('first_name')->get(),
            'acts' => ActType::orderBy('label')->get(),
            'pharmacyActs' => ActType::whereIn('service_category_id', \App\Models\Tenant\ServiceCategory::where('family', 'pharmacy')->pluck('id'))->pluck('id')->all(),
            'medicines' => \App\Models\Tenant\Medicine::orderBy('label')->get(['id', 'label', 'presentation', 'eligible', 'excluded', 'max_quantity']),
            'checkedDependent' => RightsCheck::hasIdentificationColumns() ? $c->dependent_id : null,
            'validity' => \App\Models\Tenant\MutualSetting::int('prescription_validity_days'),
        ]);
    }

    public function pharmacyStore(Request $request, int $check, \App\Services\Tenant\PharmacyService $pharmacy)
    {
        [$provider, $convention] = $this->context($request, 'act');
        $c = $this->usableCheck($request, $provider, $check);
        $member = $c->member;
        $data = $request->validate([
            'beneficiary'      => ['required', 'string'],
            'act_type_id'      => ['required', 'integer'],
            'reference'        => ['required', 'string', 'max:60'],
            'prescriber'       => ['required', 'string', 'max:160'],
            'prescribed_on'    => ['required', 'date', 'before_or_equal:today'],
            'renewals_allowed' => ['nullable', 'integer', 'between:0,12'],
            'lines'            => ['required', 'array', 'min:1', 'max:20'],
            'lines.*.medicine_id' => ['nullable', 'integer'],
            'lines.*.prescribed'  => ['nullable', 'integer', 'min:1', 'max:9999'],
            'lines.*.dispensed'   => ['nullable', 'integer', 'min:1', 'max:9999'],
            'lines.*.unit_price'  => ['nullable', 'string', 'max:20'],
        ], [
            'reference.required' => __('Indiquez le numéro de l’ordonnance.'), 'prescriber.required' => __('Indiquez le prescripteur.'),
            'prescribed_on.before_or_equal' => __('La date de l’ordonnance ne peut pas être dans le futur.'),
        ]);
        abort_unless(ActType::whereKey($data['act_type_id'])->exists(), 422, __('Acte inconnu.'));
        $dependent = null;
        if ($data['beneficiary'] !== 'member') {
            $dependent = $member->dependents()->where('status', 'active')->whereKey((int) $data['beneficiary'])->first();
            abort_unless($dependent, 422, __('Bénéficiaire inconnu.'));
        }
        $lines = [];
        foreach ($data['lines'] as $l) {
            if (empty($l['medicine_id'])) {
                continue;   // ligne vide du formulaire
            }
            if (empty($l['prescribed']) || empty($l['dispensed']) || ($l['unit_price'] ?? '') === '') {
                return back()->withInput()->withErrors(['lines' => __('Pour chaque médicament, indiquez la quantité prescrite, la quantité délivrée et le prix unitaire.')]);
            }
            $lines[] = ['medicine_id' => (int) $l['medicine_id'], 'quantity_prescribed' => (int) $l['prescribed'], 'quantity_dispensed' => (int) $l['dispensed'],
                'unit_price_minor' => $this->currency()->toMinor($this->amount($l['unit_price']))];
        }
        try {
            $res = $pharmacy->dispense($member, $dependent, $provider->id, (int) $data['act_type_id'], [
                'reference' => $data['reference'], 'prescriber' => $data['prescriber'], 'prescribed_on' => $data['prescribed_on'],
                'renewals_allowed' => (int) ($data['renewals_allowed'] ?? 0),
            ], $lines, $request->user(), 'provider', $request->boolean('emergency'));
        } catch (\App\Services\AccountingRefusal $e) {
            return back()->withInput()->withErrors(['lines' => $e->getMessage()]);
        }
        if (! $res['ok']) {
            return back()->withInput()->with('rights_block', $res['reasons']);
        }
        $p = $res['prestation'];

        return redirect()->route('portal.claims')->with('success', sprintf(
            'Délivrance enregistrée (prise en charge n° %d). Part mutuelle : %s ; à régler par le patient : %s%s. Elle sera validée par la mutuelle.',
            $p->id, $this->currency()->format($p->mutual_part_minor), $this->currency()->format($p->beneficiary_part_minor),
            $res['excluded'] > 0 ? ' (dont ' . $this->currency()->format($res['excluded']) . ' de médicaments non pris en charge)' : ''));
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

    public function invoices(Request $request, ProviderBillingService $billing)
    {
        [$provider, $convention] = $this->context($request);
        // Prestations VALIDÉES de cet établissement, pas encore facturées (ou rejetées sur une
        // facture précédente : renvoi possible après correction). Phase 5 (2026-11-26).
        $billable = $billing->billable($provider->id)->with(['careClaim.member', 'careClaim.dependent', 'actType'])
            ->orderBy('care_date')->orderBy('id')->limit(ProviderBillingService::MAX_LINES)->get();
        $q = ProviderInvoice::where('provider_id', $provider->id)->with('careClaim.member');
        if (ProviderBillingService::ready()) {
            $q->withCount(['lines', 'lines as rejected_lines_count' => fn ($l) => $l->where('status', 'rejected')]);
        }

        return view('tenant.portal.invoices', [
            'provider' => $provider, 'convention' => $convention, 'currency' => $this->currency(),
            'invoices' => $q->latest('id')->paginate(25),
            'billable' => $billable,
            'resend'   => $billing->previousRejections($billable->pluck('id')->all()),
            'ready'    => ProviderBillingService::ready(),
        ]);
    }

    /** Bordereau : plusieurs prestations validées en une facture (phase 5, 2026-11-26). */
    public function storeBatch(Request $request, ProviderBillingService $billing)
    {
        [$provider] = $this->context($request, 'bill');
        abort_unless(ProviderBillingService::ready(), 404);
        $data = $request->validate([
            'prestations' => ['required', 'array', 'min:1', 'max:' . ProviderBillingService::MAX_LINES], 'prestations.*' => ['integer'],
            'provider_reference' => ['nullable', 'string', 'max:60'],
        ] + InvoiceDocumentService::rules(), InvoiceDocumentService::messages() + ['prestations.required' => __('Cochez au moins une prestation à facturer.')]);
        try {
            $inv = $billing->submit($provider->id, $data['prestations'], $request->user(), $data['provider_reference'] ?? null, $request->file('document'), 'batch');
        } catch (AccountingRefusal $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('portal.invoices.show', $inv->id)->with('success', __('Facture :ref transmise à la mutuelle (:amount). Elle sera contrôlée ligne par ligne ; le paiement est fait par la mutuelle, hors de la plateforme.',
            ['ref' => $inv->reference, 'amount' => $this->currency()->format($inv->amount_minor)]));
    }

    /** Une prise en charge entière (ancien bouton conservé) : même circuit, une facture à lignes. */
    public function requestPayment(Request $request, int $claim, InvoiceDocumentService $docs, ProviderBillingService $billing)
    {
        [$provider, $convention] = $this->context($request, 'bill');
        $data = $request->validate(['provider_reference' => ['nullable', 'string', 'max:60']] + InvoiceDocumentService::rules(), InvoiceDocumentService::messages());
        $c = CareClaim::findOrFail($claim);

        if (! ProviderBillingService::ready()) {
            return $this->legacyRequestPayment($request, $provider, $c, $data, $docs);
        }
        $ids = $billing->billable($provider->id)->where('care_claim_id', $c->id)->pluck('id')->all();
        abort_if(! $ids, 422, __('Cette prise en charge est déjà facturée, ou n’a aucune prestation validée à facturer.'));
        try {
            $inv = $billing->submit($provider->id, $ids, $request->user(), $data['provider_reference'] ?? null, $request->file('document'), 'claim');
        } catch (AccountingRefusal $e) {
            abort(422, $e->getMessage());
        }

        return back()->with('success', __('Demande de paiement :ref transmise à la mutuelle (:amount). Le paiement est fait par la mutuelle, hors de la plateforme.', ['ref' => $inv->reference, 'amount' => $this->currency()->format($inv->amount_minor)]));
    }

    /** Détail d'une facture : lignes, motifs des rejets, historique, paiement ; imprimable (bordereau). */
    public function showInvoice(Request $request, int $invoice)
    {
        [$provider, $convention] = $this->context($request);
        $inv = ProviderInvoice::where('provider_id', $provider->id)->findOrFail($invoice);
        $ready = ProviderBillingService::ready();

        return view('tenant.portal.invoice', [
            'provider' => $provider, 'convention' => $convention, 'currency' => $this->currency(), 'inv' => $inv,
            'lines'  => $ready ? $inv->lines()->with(['prestation.actType', 'prestation.careClaim.member', 'prestation.careClaim.dependent'])->orderBy('care_date')->orderBy('id')->get() : collect(),
            'events' => $ready ? $inv->events()->get() : collect(),
        ]);
    }

    /** Export CSV des lignes facturées par cet établissement (tableur, rapprochement des paiements). */
    public function exportInvoices(Request $request)
    {
        [$provider] = $this->context($request);
        abort_unless(ProviderBillingService::ready(), 404);
        $cur = $this->currency();
        $amount = fn ($v) => number_format(((int) $v) / (10 ** $cur->minor_unit), $cur->minor_unit, ',', '');
        $lines = \App\Models\Tenant\ProviderInvoiceLine::with(['invoice', 'prestation.careClaim.member', 'prestation.careClaim.dependent'])
            ->whereHas('invoice', fn ($q) => $q->where('provider_id', $provider->id))->orderBy('provider_invoice_id')->orderBy('id')->get();

        $out = fopen('php://temp', 'w+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Facture', 'Votre réf.', 'Envoyée le', 'État de la facture', 'Prestation n°', 'Date des soins', 'Bénéficiaire', 'Code adhérent',
            'Montant', 'Devise', 'État de la ligne', 'Motif', 'Payée le', 'Réf. du paiement'], ';');
        foreach ($lines as $l) {
            $i = $l->invoice;
            $claim = $l->prestation?->careClaim;
            $who = $claim?->dependent ? trim($claim->dependent->last_name . ' ' . $claim->dependent->first_name) : trim(($claim?->member?->last_name ?? '') . ' ' . ($claim?->member?->first_name ?? ''));
            fputcsv($out, [$i?->label(), $i?->provider_reference, $i?->created_at?->format('d/m/Y'), ProviderInvoice::STATUSES[$i?->status] ?? $i?->status,
                $l->prestation_id, $l->care_date?->format('d/m/Y'), $who, $claim?->member?->member_code, $amount($l->amount_minor), $cur->code,
                \App\Models\Tenant\ProviderInvoiceLine::STATUSES[$l->status] ?? $l->status, $l->decision_note, $i?->paid_on?->format('d/m/Y'), $i?->payment_reference], ';');
        }
        rewind($out);

        return response(stream_get_contents($out), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="mes-factures-' . now()->format('Ymd') . '.csv"',
        ])->setPrivate();
    }

    /** Joindre (ou remplacer) la facture PDF tant que la mutuelle n'a pas payé. */
    public function attachDocument(Request $request, int $invoice, InvoiceDocumentService $docs, ProviderBillingService $billing)
    {
        [$provider, $convention] = $this->context($request, 'bill');
        $inv = ProviderInvoice::where('provider_id', $provider->id)->findOrFail($invoice);
        abort_unless(in_array($inv->status, ['captured', 'processed'], true), 422, __('Cette facture est déjà en paiement : la pièce ne se modifie plus.'));
        $request->validate(['document' => ['required', ...InvoiceDocumentService::rules()['document']]], InvoiceDocumentService::messages() + ['document.required' => __('Choisissez le fichier PDF de la facture.')]);
        $docs->attach($inv, $request->file('document'));
        $billing->event($inv, 'document', $request->user()->id);

        return back()->with('success', __('Facture PDF jointe à la demande :label.', ['label' => $inv->label()]));
    }

    /** Ancien circuit (mutuelle pas encore migrée) : une facture par prise en charge, sans ligne. */
    private function legacyRequestPayment(Request $request, CareProvider $provider, CareClaim $c, array $data, InvoiceDocumentService $docs)
    {
        $amount = DB::transaction(function () use ($provider, $c, $request, $data, $docs) {
            CareClaim::whereKey($c->id)->lockForUpdate()->first();
            abort_if(ProviderInvoice::where('provider_id', $provider->id)->where('care_claim_id', $c->id)->exists(), 422, 'Cette prise en charge est déjà facturée.');
            $amount = (int) $c->prestations()->where('provider_id', $provider->id)->where('status', 'validated')->sum('mutual_part_minor');
            abort_if($amount <= 0, 422, __('Aucune prestation validée à facturer.'));
            $invoice = ProviderInvoice::create([
                'provider_id' => $provider->id, 'care_claim_id' => $c->id, 'amount_minor' => $amount, 'status' => 'captured',
                'captured_by' => $request->user()->id, 'provider_reference' => $data['provider_reference'] ?? null,
            ]);
            if ($request->hasFile('document')) {
                $docs->attach($invoice, $request->file('document'));
            }

            return $amount;
        });

        return back()->with('success', __('Demande de paiement transmise à la mutuelle (:amount). Le paiement est fait par la mutuelle, hors de la plateforme.', ['amount' => $this->currency()->format($amount)]));
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
        abort_unless($provider && $convention, 403, __('Ce compte n’est rattaché à aucun prestataire conventionné. Contactez la mutuelle.'));

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
        abort_if($mode === 'act' && ! $canAct, 403, __('La convention de votre établissement avec la mutuelle n’est pas en vigueur. Contactez la mutuelle.'));
        abort_if($mode === 'bill' && ! $canBill, 403, __('La convention de votre établissement a été suspendue par la mutuelle : la facturation en ligne est fermée. Contactez la mutuelle.'));

        return [$provider, $convention];
    }

    /** Vérification positive, faite par CE compte pour CE prestataire, il y a moins de 12 heures. */
    private function usableCheck(Request $request, CareProvider $provider, int $check): RightsCheck
    {
        $c = RightsCheck::with('member')->whereKey($check)->where('user_id', $request->user()->id)
            ->where('care_provider_id', $provider->id)->where('ok', true)->whereNotNull('member_id')->first();
        abort_unless($c, 404);
        abort_if($c->created_at->lt(now()->subHours(self::CHECK_HOURS)), 410, __('Cette vérification a expiré : vérifiez de nouveau les droits de l’adhérent.'));

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
