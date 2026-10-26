<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Concerns\MutualContext;
use App\Models\Central\CareProvider;
use App\Models\Tenant\ActType;
use App\Models\Tenant\CareClaim;
use App\Models\Tenant\Member;
use App\Models\Tenant\Prestation;
use App\Models\Tenant\ProviderConvention;
use App\Models\Tenant\ProviderInvoice;
use App\Models\Tenant\RightsCheck;
use App\Services\ClaimCaptureService;
use App\Services\DigitalCardService;
use App\Services\RightsVerificationService;
use App\Services\Tenant\InvoiceDocumentService;
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

        return view('tenant.portal.index', [
            'provider' => $provider, 'convention' => $convention, 'result' => null,
            'recent'   => RightsCheck::with('member')->where('care_provider_id', $provider->id)->where('user_id', $request->user()->id)
                ->latest('id')->limit(8)->get(),
        ]);
    }

    public function verify(Request $request, DigitalCardService $cards)
    {
        [$provider, $convention] = $this->context($request, true);
        $data = $request->validate(['code' => ['required', 'string', 'max:500']], ['code.required' => 'Saisissez le code adhérent ou collez le lien de la carte.']);
        $input = trim($data['code']);

        // Lien ou jeton de la carte (QR) : signature vérifiée ; sinon code adhérent imprimé sur la carte.
        $member = null;
        if (preg_match('~[?&]t=([^&\s]+)~', $input, $m) || substr_count($input, '.') === 2) {
            $token = isset($m[1]) ? urldecode($m[1]) : $input;
            $res = $cards->verifyToken($token);
            $member = $res['valid'] ? Member::find($res['member_id']) : null;
        } else {
            $member = Member::where('member_code', $input)->first();
        }

        if (! $member) {
            $check = RightsCheck::create(['user_id' => $request->user()->id, 'care_provider_id' => $provider->id, 'member_id' => null,
                'ok' => false, 'reasons' => ['unknown_member'], 'created_at' => now()]);

            return $this->resultView($request, $provider, $convention, ['ok' => false, 'reasons' => ['unknown_member'], 'member' => null, 'check' => $check]);
        }

        $version = $this->capture->versionFor($member, now()->toDateString());
        $verdict = $version
            ? $this->rights->verify($member, $version->guarantee_id, now()->toDateString(), null, $provider->id)
            : ['ok' => false, 'reasons' => ['no_active_guarantee']];

        $check = RightsCheck::create(['user_id' => $request->user()->id, 'care_provider_id' => $provider->id, 'member_id' => $member->id,
            'ok' => $verdict['ok'], 'reasons' => $verdict['reasons'], 'created_at' => now()]);

        return $this->resultView($request, $provider, $convention, $verdict + ['member' => $member, 'check' => $check]);
    }

    public function declare(Request $request, int $check)
    {
        [$provider, $convention] = $this->context($request, true);
        $c = $this->usableCheck($request, $provider, $check);

        return view('tenant.portal.declare', [
            'provider' => $provider, 'check' => $c, 'member' => $c->member,
            'dependents' => $c->member->dependents()->where('status', 'active')->orderBy('first_name')->get(),
            'acts' => ActType::orderBy('label')->get(),
            'currency' => $this->currency(),
        ]);
    }

    public function storeDeclaration(Request $request, int $check)
    {
        [$provider, $convention] = $this->context($request, true);
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
        [$provider, $convention] = $this->context($request, true);
        $data = $request->validate(['provider_reference' => ['nullable', 'string', 'max:60']] + InvoiceDocumentService::rules(), InvoiceDocumentService::messages());
        $c = CareClaim::findOrFail($claim);
        abort_if(ProviderInvoice::where('provider_id', $provider->id)->where('care_claim_id', $c->id)->exists(), 422, 'Cette prise en charge est déjà facturée.');
        $amount = (int) $c->prestations()->where('provider_id', $provider->id)->where('status', 'validated')->sum('mutual_part_minor');
        abort_if($amount <= 0, 422, 'Aucune prestation validée à facturer.');

        // Facture et pièce ensemble : un PDF refusé n'enregistre aucune demande.
        DB::transaction(function () use ($provider, $c, $amount, $request, $data, $docs) {
            $invoice = ProviderInvoice::create([
                'provider_id' => $provider->id, 'care_claim_id' => $c->id, 'amount_minor' => $amount, 'status' => 'captured',
                'captured_by' => $request->user()->id, 'provider_reference' => $data['provider_reference'] ?? null,
            ]);
            if ($request->hasFile('document')) {
                $docs->attach($invoice, $request->file('document'));
            }
        });

        return back()->with('success', 'Demande de paiement transmise à la mutuelle (' . $this->currency()->format($amount) . '). Le paiement est fait par la mutuelle, hors de la plateforme.');
    }

    /** Joindre (ou remplacer) la facture PDF tant que la mutuelle n'a pas payé. */
    public function attachDocument(Request $request, int $invoice, InvoiceDocumentService $docs)
    {
        [$provider, $convention] = $this->context($request, true);
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
     * Prestataire représenté par le compte connecté et sa convention. Sans convention en
     * vigueur : consultation seule ($act = false) ou refus ($act = true).
     *
     * @return array{0:CareProvider,1:ProviderConvention}
     */
    private function context(Request $request, bool $act = false): array
    {
        $user = $request->user();
        $provider = $user->care_provider_id ? CareProvider::on('central')->find($user->care_provider_id) : null;
        $convention = ProviderConvention::forProvider($provider?->id);
        abort_unless($provider && $convention, 403, 'Ce compte n’est rattaché à aucun prestataire conventionné. Contactez la mutuelle.');
        abort_if($act && ! $convention->inForce(), 403, 'La convention de votre établissement avec la mutuelle n’est pas en vigueur. Contactez la mutuelle.');

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

    private function resultView(Request $request, CareProvider $provider, ProviderConvention $convention, array $result)
    {
        return view('tenant.portal.index', [
            'provider' => $provider, 'convention' => $convention, 'result' => $result,
            'recent'   => RightsCheck::with('member')->where('care_provider_id', $provider->id)->where('user_id', $request->user()->id)
                ->latest('id')->limit(8)->get(),
        ]);
    }
}
