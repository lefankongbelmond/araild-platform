<?php

namespace App\Services\Tenant;

use App\Models\Central\CareProvider;
use App\Models\Tenant\ActType;
use App\Models\Tenant\ContributionPayment;
use App\Models\Tenant\ContributionSchedule;
use App\Models\Tenant\Dependent;
use App\Models\Tenant\Member;
use App\Models\Tenant\Prestation;
use App\Models\Tenant\ProviderInvoice;
use App\Models\Tenant\Subscription;
use App\Models\Tenant\TreasuryMovement;
use Illuminate\Support\Carbon;

/**
 * Rapport d'activité d'une mutuelle sur une période (2026-11-03). Uniquement des chiffres
 * réels, calculés à la demande depuis la base de la mutuelle ; montants en unités mineures
 * de la devise de la mutuelle (jamais convertis). Le détail par acte est un agrégat ; il
 * n'est affiché qu'aux rôles qui voient le détail médical.
 */
class ActivityReportService
{
    /** Périodes proposées : clé => [libellé, du, au]. */
    public static function periods(): array
    {
        $now = now();

        return [
            'mois'            => ['Ce mois-ci', $now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'mois-precedent'  => ['Le mois dernier', $now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'trimestre'       => ['Ce trimestre', $now->copy()->firstOfQuarter(), $now->copy()->lastOfQuarter()],
            'annee'           => ['Cette année', $now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'annee-precedente' => ['L’année dernière', $now->copy()->subYear()->startOfYear(), $now->copy()->subYear()->endOfYear()],
        ];
    }

    public function build(Carbon $from, Carbon $to): array
    {
        [$f, $t] = [$from->toDateString(), $to->toDateString()];

        $called = ContributionSchedule::whereBetween('due_date', [$f, $t]);
        $calledMinor = (int) (clone $called)->sum('due_minor');
        $paidOnSchedules = (int) ContributionPayment::whereIn('schedule_id', (clone $called)->select('id'))->sum('amount_minor');
        $collected = (int) ContributionPayment::whereBetween('paid_on', [$f, $t])->sum('amount_minor');

        $presta = Prestation::whereBetween('care_date', [$f, $t]);
        $validated = (clone $presta)->where('status', 'validated');
        $mutualPart = (int) (clone $validated)->sum('mutual_part_minor');

        $byAct = (clone $validated)->selectRaw('act_type_id, count(*) n, sum(mutual_part_minor) m')->groupBy('act_type_id')
            ->orderByDesc('m')->get();
        $acts = ActType::withTrashed()->whereIn('id', $byAct->pluck('act_type_id'))->pluck('label', 'id');
        $byProvider = (clone $validated)->selectRaw('provider_id, count(*) n, sum(mutual_part_minor) m')->groupBy('provider_id')
            ->orderByDesc('m')->limit(15)->get();
        $providers = CareProvider::on('central')->whereIn('id', $byProvider->pluck('provider_id'))->pluck('name', 'id');

        $moves = TreasuryMovement::whereBetween('moved_on', [$f, $t]);

        return [
            'from' => $from, 'to' => $to,
            'members' => [
                'active'      => Member::where('status', 'active')->count(),
                'new'         => Member::whereBetween('joined_at', [$f, $t])->count(),
                'dependents'  => Dependent::where('status', 'active')->whereHas('member', fn ($q) => $q->where('status', 'active'))->count(),
                'suspended'   => Member::where('status', 'suspended')->count(),
            ],
            'subscriptions' => [
                'validated' => Subscription::where('status', 'validated')->whereBetween('validated_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->count(),
                'pending'   => Subscription::where('status', 'captured')->count(),
            ],
            'contributions' => [
                'called'    => $calledMinor,
                'collected_on_called' => $paidOnSchedules,
                'collected' => $collected,
                'rate'      => $calledMinor > 0 ? round($paidOnSchedules * 100 / $calledMinor, 1) : null,
                'overdue'   => (int) ContributionSchedule::where('status', 'overdue')->sum('due_minor'),
            ],
            'prestations' => [
                'validated'   => (clone $validated)->count(),
                'total'       => (int) (clone $validated)->sum('total_minor'),
                'mutual'      => $mutualPart,
                'beneficiary' => (int) (clone $validated)->sum('beneficiary_part_minor'),
                'pending'     => (clone $presta)->where('status', 'captured')->count(),
                'rejected'    => (clone $presta)->where('status', 'rejected')->count(),
                'claims_ratio' => $collected > 0 ? round($mutualPart * 100 / $collected, 1) : null,
            ],
            'by_act' => $byAct->map(fn ($r) => ['label' => $acts[$r->act_type_id] ?? 'Acte n° ' . $r->act_type_id, 'count' => (int) $r->n, 'mutual' => (int) $r->m])->all(),
            'by_provider' => $byProvider->map(fn ($r) => ['label' => $providers[$r->provider_id] ?? 'Prestataire n° ' . $r->provider_id, 'count' => (int) $r->n, 'mutual' => (int) $r->m])->all(),
            'invoices' => [
                'paid'    => (int) ProviderInvoice::where('status', 'paid')->whereBetween('updated_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->sum('amount_minor'),
                'payable' => (int) ProviderInvoice::whereIn('status', ProviderInvoice::PAYABLE)->sum('amount_minor'),
            ],
            'treasury' => [
                'in'  => (int) (clone $moves)->where('direction', 'inflow')->sum('amount_minor'),
                'out' => (int) (clone $moves)->where('direction', 'outflow')->sum('amount_minor'),
            ],
        ];
    }
}
