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
use Illuminate\Support\Facades\DB;

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

    /** Nombre maximal de mois affichés dans l'évolution mensuelle (les plus récents de la période). */
    public const MAX_MONTHS = 24;

    /**
     * Évolution mois par mois (2026-11-04) : nouveaux adhérents, cotisations encaissées,
     * prestations validées et part de la mutuelle, entrées et sorties de trésorerie.
     * Une requête groupée par indicateur, jamais une par mois.
     *
     * @return array<string, array{label:string,new:int,collected:int,prestations:int,mutual:int,in:int,out:int}>
     */
    public function monthly(Carbon $from, Carbon $to): array
    {
        $start = $from->copy()->startOfMonth();
        if ($start->diffInMonths($to->copy()->startOfMonth()) >= self::MAX_MONTHS) {
            $start = $to->copy()->startOfMonth()->subMonthsNoOverflow(self::MAX_MONTHS - 1);
        }
        $f = ($start->gt($from) ? $start : $from)->toDateString();
        $t = $to->toDateString();
        $g = DB::connection()->getQueryGrammar();
        $ym = fn (string $col) => 'date_format(' . $g->wrap($col) . ", '%Y-%m')";
        $by = fn ($query, string $dateCol, string $agg) => $query->selectRaw($ym($dateCol) . ' as ym, ' . $agg . ' as v')
            ->groupByRaw($ym($dateCol))->pluck('v', 'ym');

        $new = $by(Member::query()->whereBetween('joined_at', [$f, $t]), 'joined_at', 'count(*)');
        $collected = $by(ContributionPayment::query()->whereBetween('paid_on', [$f, $t])
            ->when(\Illuminate\Support\Facades\Schema::hasColumn('contribution_schedules', 'kind'),
                fn ($q) => $q->whereIn('schedule_id', ContributionSchedule::where('kind', 'contribution')->select('id'))),
            'paid_on', 'sum(' . $g->wrap('amount_minor') . ')');
        $validated = Prestation::query()->where('status', 'validated')->whereBetween('care_date', [$f, $t]);
        $prestations = $by((clone $validated), 'care_date', 'count(*)');
        $mutual = $by((clone $validated), 'care_date', 'sum(' . $g->wrap('mutual_part_minor') . ')');
        $moves = TreasuryMovement::query()->whereBetween('moved_on', [$f, $t]);
        $in = $by((clone $moves)->where('direction', 'inflow'), 'moved_on', 'sum(' . $g->wrap('amount_minor') . ')');
        $out = $by((clone $moves)->where('direction', 'outflow'), 'moved_on', 'sum(' . $g->wrap('amount_minor') . ')');

        $months = [];
        for ($m = $start->copy(); $m->lte($to); $m->addMonthNoOverflow()) {
            $k = $m->format('Y-m');
            $months[$k] = [
                'label' => ucfirst($m->locale('fr')->translatedFormat('M Y')),
                'new' => (int) ($new[$k] ?? 0), 'collected' => (int) ($collected[$k] ?? 0),
                'prestations' => (int) ($prestations[$k] ?? 0), 'mutual' => (int) ($mutual[$k] ?? 0),
                'in' => (int) ($in[$k] ?? 0), 'out' => (int) ($out[$k] ?? 0),
            ];
        }

        return $months;
    }

    public function build(Carbon $from, Carbon $to): array
    {
        [$f, $t] = [$from->toDateString(), $to->toDateString()];

        // Cotisations seulement ; droits d'adhésion à part (2026-11-07).
        $hasKind = \Illuminate\Support\Facades\Schema::hasColumn('contribution_schedules', 'kind');
        $contrib = fn ($q) => $hasKind ? $q->where('kind', 'contribution') : $q;
        $called = $contrib(ContributionSchedule::whereBetween('due_date', [$f, $t]));
        $calledMinor = (int) (clone $called)->sum('due_minor');
        $paidOnSchedules = (int) ContributionPayment::whereIn('schedule_id', (clone $called)->select('id'))->sum('amount_minor');
        $collectedAll = (int) ContributionPayment::whereBetween('paid_on', [$f, $t])->sum('amount_minor');
        $fees = $hasKind ? (int) ContributionPayment::whereBetween('paid_on', [$f, $t])
            ->whereIn('schedule_id', ContributionSchedule::where('kind', 'membership_fee')->select('id'))->sum('amount_minor') : 0;
        $collected = $collectedAll - $fees;

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
            'monthly' => $this->monthly($from, $to),
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
                'fees'      => $fees,
                'rate'      => $calledMinor > 0 ? round($paidOnSchedules * 100 / $calledMinor, 1) : null,
                // Reste dû sur les échéances en retard (paiements partiels déduits, 2026-11-06).
                'overdue'   => (int) ContributionSchedule::where('status', 'overdue')->sum('due_minor')
                    - (int) ContributionPayment::whereIn('schedule_id', ContributionSchedule::where('status', 'overdue')->select('id'))->sum('amount_minor'),
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
