<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Concerns\MutualContext;
use App\Models\Tenant\ActType;
use App\Models\Tenant\ContributionPayment;
use App\Models\Tenant\ContributionSchedule;
use App\Models\Tenant\Guarantee;
use App\Models\Tenant\GuaranteeVersion;
use App\Models\Tenant\Medicine;
use App\Models\Tenant\Member;
use App\Models\Tenant\Prestation;
use App\Models\Tenant\ProviderInvoice;
use App\Services\ClaimAssessmentService;
use App\Services\RightsVerificationService;
use Illuminate\Http\Request;

/**
 * Facturation : tableau de bord des cotisations (appelées, encaissées, en retard),
 * des factures des formations sanitaires et des prestations ; calculateur de
 * quote-part (part mutuelle / part adhérent) en formation sanitaire.
 */
class BillingController extends Controller
{
    use MutualContext;

    // Droits : matrice App\Support\TenantAccess (« billing.* »).

    public const RIGHTS = [
        'member_inactive'         => 'Adhérent non actif (suspendu, radié ou parti).',
        'contribution_arrears'    => 'Cotisations en retard : droits suspendus jusqu’à régularisation.',
        'no_active_guarantee'     => 'Aucune garantie applicable à la date des soins.',
        'observation_period'      => 'Stage d’attente en cours : la garantie n’est pas encore ouverte.',
        'act_excluded'            => 'Acte exclu de la garantie.',
        'provider_not_authorised' => 'Formation sanitaire hors du réseau de la garantie.',
    ];

    public function dashboard(Request $request)
    {
        \App\Support\TenantAccess::authorize();
        $year = (int) $request->get('annee', now()->year);
        $currency = $this->currency();

        $called = (int) ContributionSchedule::where('year', $year)->sum('due_minor');
        $paidQ = ContributionPayment::whereYear('paid_on', $year);
        $collected = (int) (clone $paidQ)->sum('amount_minor');
        $overdue = ContributionSchedule::where('status', 'overdue');
        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $months[$m] = [
                'collected' => (int) ContributionPayment::whereYear('paid_on', $year)->whereMonth('paid_on', $m)->sum('amount_minor'),
                'called'    => (int) ContributionSchedule::where('year', $year)->whereMonth('due_date', $m)->sum('due_minor'),
            ];
        }
        $invoiceStatuses = ['captured' => 'Saisies', 'processed' => 'Traitées', 'prepared' => 'Prêtes à payer', 'paid' => 'Payées', 'rejected' => 'Rejetées'];
        $invoices = ProviderInvoice::whereYear('created_at', $year)->selectRaw('status, count(*) as n, sum(amount_minor) as total')
            ->groupBy('status')->get()->keyBy('status');
        $prest = Prestation::whereYear('care_date', $year)->where('status', '!=', 'rejected')
            ->selectRaw('count(*) as n, coalesce(sum(total_minor),0) as total, coalesce(sum(mutual_part_minor),0) as mutual, coalesce(sum(beneficiary_part_minor),0) as member')->first();

        return view('tenant.billing.dashboard', [
            'year' => $year, 'currency' => $currency,
            'kpi' => [
                'called' => $called, 'collected' => $collected,
                'rate' => $called > 0 ? round($collected * 100 / $called, 1) : null,
                'overdue_amount' => (int) (clone $overdue)->sum('due_minor'), 'overdue_count' => (clone $overdue)->count(),
                'payments' => (clone $paidQ)->count(),
            ],
            'months' => $months, 'invoices' => $invoices, 'invoiceStatuses' => $invoiceStatuses, 'prest' => $prest,
            'recentPayments' => ContributionPayment::with('schedule.subscription.member')->latest('paid_on')->limit(8)->get(),
        ]);
    }

    /** Calculateur de quote-part : formulaire. */
    public function quote(Request $request)
    {
        \App\Support\TenantAccess::authorize();

        return view('tenant.billing.quote', $this->quoteData() + ['result' => null]);
    }

    /** Calculateur de quote-part : calcul (simulation, aucune écriture). */
    public function computeQuote(Request $request, ClaimAssessmentService $assessment, RightsVerificationService $rights)
    {
        \App\Support\TenantAccess::authorize();
        $data = $request->validate([
            'member_code'  => ['nullable', 'string', 'max:40'],
            'guarantee_id' => ['nullable', 'integer'],
            'care_date'    => ['required', 'date'],
            'facility'     => ['nullable', 'string', 'max:150'],
            'acts'         => ['array'], 'acts.*.id' => ['nullable', 'integer'], 'acts.*.amount' => ['nullable', 'string', 'max:20'], 'acts.*.qty' => ['nullable', 'integer', 'min:1', 'max:99'],
            'meds'         => ['array'], 'meds.*.id' => ['nullable', 'integer'], 'meds.*.qty' => ['nullable', 'integer', 'min:1', 'max:999'], 'meds.*.price' => ['nullable', 'string', 'max:20'],
        ], [], ['care_date' => 'date des soins']);
        $currency = $this->currency();
        $date = substr($data['care_date'], 0, 10);
        $errors = [];

        // Adhérent (facultatif) → garantie de sa souscription validée.
        $member = null;
        $guaranteeId = $data['guarantee_id'] ?? null;
        if (! empty($data['member_code'])) {
            $member = Member::where('member_code', trim($data['member_code']))->first();
            if (! $member) {
                $errors[] = 'Aucun adhérent avec le code « ' . e($data['member_code']) . ' ».';
            } else {
                $sub = $member->subscriptions()->where('status', 'validated')->latest('effective_date')->first();
                $guaranteeId = $sub?->guaranteeVersion?->guarantee_id ?? $guaranteeId;
                if (! $sub) {
                    $errors[] = 'Cet adhérent n’a pas de souscription validée.';
                }
            }
        }
        $version = $guaranteeId ? GuaranteeVersion::applicableOn((int) $guaranteeId, $date) : null;
        if (! $version) {
            $errors[] = 'Choisissez un adhérent ou une garantie applicable à la date des soins.';
        }

        $lines = [];
        if ($version) {
            $pharmacyAct = ActType::where('code', 'PHA-MED')->value('id');
            foreach ((array) ($data['acts'] ?? []) as $row) {
                if (empty($row['id'])) continue;
                $act = ActType::find($row['id']);
                $qty = max(1, (int) ($row['qty'] ?? 1));
                $unit = $row['amount'] !== null && $row['amount'] !== '' ? $currency->toMinor($this->amount($row['amount'])) : (int) ($act?->tariff_minor ?? 0);
                if (! $act || $unit <= 0) { $errors[] = 'Montant manquant pour l’acte « ' . ($act?->label ?? '?') . ' ».'; continue; }
                $total = $unit * $qty;
                $split = $assessment->assess($version, $act->id, $total, $member?->id ?? 0, $date);
                $lines[] = ['type' => 'Acte', 'label' => $act->label, 'qty' => $qty, 'unit' => $unit, 'total' => $total] + $split;
            }
            foreach ((array) ($data['meds'] ?? []) as $row) {
                if (empty($row['id'])) continue;
                $med = Medicine::find($row['id']);
                $qty = max(1, (int) ($row['qty'] ?? 1));
                if (! $med) continue;
                $medCur = $med->currency_code ?: $currency->code;
                $unit = $row['price'] !== null && $row['price'] !== '' ? $currency->toMinor($this->amount($row['price']))
                    : ($medCur === $currency->code ? (int) $med->reference_price_minor : 0);
                if ($unit <= 0) {
                    $errors[] = $medCur !== $currency->code && ($row['price'] ?? '') === ''
                        ? '« ' . $med->label . ' » : prix de référence en ' . $medCur . ', saisissez le prix en ' . $currency->code . ' (pas de conversion automatique).'
                        : '« ' . $med->label . ' » : prix à renseigner.';
                    continue;
                }
                $total = $unit * $qty;
                $split = $assessment->assess($version, (int) ($pharmacyAct ?? 0), $total, $member?->id ?? 0, $date);
                $lines[] = ['type' => 'Médicament', 'label' => $med->label . ($med->presentation ? ' — ' . $med->presentation : ''), 'qty' => $qty, 'unit' => $unit, 'total' => $total] + $split;
            }
            if (! $lines && ! $errors) {
                $errors[] = 'Ajoutez au moins un acte ou un médicament.';
            }
        }

        $verdict = ($member && $version) ? $rights->verify($member, $version->guarantee_id, $date) : null;
        $result = [
            'member' => $member, 'version' => $version?->load('guarantee'), 'date' => $date, 'facility' => $data['facility'] ?? null,
            'lines' => $lines, 'errors' => $errors, 'verdict' => $verdict,
            'total' => array_sum(array_column($lines, 'total')),
            'mutual' => array_sum(array_column($lines, 'mutual_minor')),
            'member_part' => array_sum(array_column($lines, 'beneficiary_minor')),
            'reference' => 'QP-' . now()->format('ymd-His'),
        ];

        return view('tenant.billing.quote', $this->quoteData() + ['result' => $result]);
    }

    private function quoteData(): array
    {
        return [
            'currency'   => $this->currency(),
            'guarantees' => Guarantee::where('status', 'active')->orderBy('name')->get(),
            'acts'       => ActType::orderBy('category')->orderBy('label')->get(),
            'medicines'  => Medicine::orderBy('label')->get(),
            'rightsLabels' => self::RIGHTS,
        ];
    }
}
