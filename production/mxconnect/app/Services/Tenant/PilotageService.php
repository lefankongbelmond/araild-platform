<?php

namespace App\Services\Tenant;

use App\Models\Central\CareProvider;
use App\Models\Tenant\CareClaim;
use App\Models\Tenant\ContributionPayment;
use App\Models\Tenant\ContributionSchedule;
use App\Models\Tenant\CoverageLimit;
use App\Models\Tenant\CoverageRule;
use App\Models\Tenant\Dependent;
use App\Models\Tenant\Dispensation;
use App\Models\Tenant\DispensationLine;
use App\Models\Tenant\Guarantee;
use App\Models\Tenant\GuaranteeVersion;
use App\Models\Tenant\Medicine;
use App\Models\Tenant\Member;
use App\Models\Tenant\Prestation;
use App\Models\Tenant\Subscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TABLEAU DE BORD DE PILOTAGE (mission « Mutuelle paramétrable », L5, 2026-11-20).
 *
 * Complète le rapport d'activité (ActivityReportService, inchangé) avec des FILTRES (formule,
 * adhérent, prestataire, antenne, type de soins, statut de traitement) et les indicateurs de la
 * mission : situation des adhésions, tickets modérateurs, consommation par formule, par garantie,
 * pharmaceutique, plafonds consommés, taux de sinistralité. Uniquement des chiffres réels, calculés à
 * la demande ; montants en unités mineures de la devise de la mutuelle. Requêtes groupées (jamais
 * une requête par adhérent) et SQL brut limité à des noms de colonnes (tables préfixées).
 */
class PilotageService
{
    public const STATUSES = ['captured' => 'Saisie (en attente)', 'controlled' => 'Contrôlée (en attente)', 'validated' => 'Validée', 'rejected' => 'Rejetée'];

    /**
     * @param array{guarantee_id?:?int, member_id?:?int, provider_id?:?int, antenna_id?:?int, category_id?:?int, status?:?string} $f
     */
    public function build(Carbon $from, Carbon $to, array $f): array
    {
        [$fd, $td] = [$from->toDateString(), $to->toDateString()];
        $today = now()->toDateString();
        $versionIds = ! empty($f['guarantee_id']) ? GuaranteeVersion::where('guarantee_id', $f['guarantee_id'])->pluck('id') : null;

        // ---------------------------------------------------------------- périmètre des adhérents
        $members = Member::query()
            ->when($f['antenna_id'] ?? null, fn ($q, $a) => $q->where('antenna_id', $a))
            ->when($f['member_id'] ?? null, fn ($q, $id) => $q->whereKey($id))
            ->when($versionIds, fn ($q) => $q->whereIn('id', Subscription::whereIn('guarantee_version_id', $versionIds)->whereIn('status', ['captured', 'validated'])->select('member_id')));
        $memberIds = (clone $members)->pluck('id');
        $scoped = ! empty($f['antenna_id']) || ! empty($f['member_id']) || $versionIds;

        $validatedNow = Subscription::where('status', 'validated')->whereDate('effective_date', '<=', $today)->whereIn('member_id', $memberIds)->pluck('member_id')->unique();
        $pending = Subscription::where('status', 'captured')->whereIn('member_id', $memberIds)->pluck('member_id')->unique();
        $arrears = Subscription::where('status', 'validated')->whereIn('member_id', $memberIds)
            ->whereHas('schedules', fn ($q) => $q->where(fn ($w) => $w->where('status', 'overdue')->orWhere(fn ($x) => $x->where('status', 'to_pay')->whereDate('due_date', '<', $today))))
            ->pluck('member_id')->unique();
        $statusOf = (clone $members)->pluck('status', 'id');
        $count = ['active' => 0, 'suspended' => 0, 'expired' => 0, 'pending' => 0];
        foreach ($statusOf as $id => $st) {
            $st = $st instanceof \BackedEnum ? $st->value : (string) $st;
            if (in_array($st, ['left', 'revoked'], true)) {
                $count['expired']++;
            } elseif ($st === 'suspended' || ($validatedNow->contains($id) && $arrears->contains($id))) {
                $count['suspended']++;
            } elseif ($validatedNow->contains($id)) {
                $count['active']++;
            } elseif ($pending->contains($id)) {
                $count['pending']++;
            } else {
                $count['expired']++;
            }
        }
        $dependents = Dependent::where('status', 'active')->whereIn('member_id', $memberIds)->count();

        // ---------------------------------------------------------------- cotisations
        $subIds = Subscription::where('status', 'validated')->whereIn('member_id', $memberIds)
            ->when($versionIds, fn ($q) => $q->whereIn('guarantee_version_id', $versionIds))->pluck('id');
        $hasKind = Schema::hasColumn('contribution_schedules', 'kind');
        $sched = ContributionSchedule::whereIn('subscription_id', $subIds)->when($hasKind, fn ($q) => $q->where('kind', 'contribution'));
        $called = (clone $sched)->whereBetween('due_date', [$fd, $td]);
        $calledMinor = (int) (clone $called)->sum('due_minor');
        $paidOnCalled = (int) ContributionPayment::whereIn('schedule_id', (clone $called)->select('id'))->sum('amount_minor');
        $collected = (int) ContributionPayment::whereBetween('paid_on', [$fd, $td])->whereIn('schedule_id', (clone $sched)->select('id'))->sum('amount_minor');
        $overdueIds = (clone $sched)->where(fn ($w) => $w->where('status', 'overdue')->orWhere(fn ($x) => $x->where('status', 'to_pay')->whereDate('due_date', '<', $today)));
        $overdue = (int) (clone $overdueIds)->sum('due_minor') - (int) ContributionPayment::whereIn('schedule_id', (clone $overdueIds)->select('id'))->sum('amount_minor');

        // ---------------------------------------------------------------- prestations
        $presta = Prestation::query()->whereBetween('care_date', [$fd, $td])
            ->when($scoped, fn ($q) => $q->whereIn('care_claim_id', CareClaim::whereIn('member_id', $memberIds)->select('id')))
            ->when($versionIds, fn ($q) => $q->whereIn('guarantee_version_id', $versionIds))
            ->when($f['provider_id'] ?? null, fn ($q, $p) => $q->where('provider_id', $p))
            ->when($f['category_id'] ?? null, fn ($q, $c) => $q->whereIn('act_type_id', \App\Models\Tenant\ActType::withTrashed()->where('service_category_id', $c)->select('id')));
        $all = (clone $presta)->when($f['status'] ?? null, fn ($q, $s) => $q->where('status', $s));
        $validated = (clone $presta)->where('status', 'validated')->when(($f['status'] ?? null) && $f['status'] !== 'validated', fn ($q) => $q->whereRaw('1 = 0'));
        $hasCopay = Schema::hasColumn('prestations', 'copay_minor');
        $mutual = (int) (clone $validated)->sum('mutual_part_minor');
        $copay = $hasCopay
            ? (int) (clone $validated)->whereNotNull('copay_minor')->sum('copay_minor') + (int) (clone $validated)->whereNull('copay_minor')->sum('beneficiary_part_minor')
            : (int) (clone $validated)->sum('beneficiary_part_minor');
        $byStatus = (clone $presta)->selectRaw('status, count(*) as n, sum(total_minor) as t')->groupBy('status')->get()->keyBy('status');

        // Consommation par formule (versions regroupées par formule).
        $vGuar = GuaranteeVersion::pluck('guarantee_id', 'id');
        $gNames = Guarantee::withTrashed()->pluck('name', 'id');
        $byFormula = [];
        foreach ((clone $validated)->selectRaw('guarantee_version_id, count(*) as n, sum(total_minor) as t, sum(mutual_part_minor) as m')->groupBy('guarantee_version_id')->get() as $r) {
            $g = $vGuar[$r->guarantee_version_id] ?? 0;
            $byFormula[$g] ??= ['label' => $gNames[$g] ?? 'Formule n° ' . $g, 'count' => 0, 'total' => 0, 'mutual' => 0];
            $byFormula[$g]['count'] += (int) $r->n;
            $byFormula[$g]['total'] += (int) $r->t;
            $byFormula[$g]['mutual'] += (int) $r->m;
        }
        usort($byFormula, fn ($a, $b) => $b['mutual'] <=> $a['mutual']);

        // Consommation par garantie (formules paramétrées ; les autres sur une ligne « taux unique »).
        $byRule = [];
        if (Schema::hasColumn('prestations', 'coverage_rule_id')) {
            $labels = CoverageRule::whereIn('id', (clone $validated)->whereNotNull('coverage_rule_id')->select('coverage_rule_id'))->get(['id', 'label', 'rule_key'])->keyBy('id');
            foreach ((clone $validated)->selectRaw('coverage_rule_id, count(*) as n, sum(total_minor) as t, sum(mutual_part_minor) as m')->groupBy('coverage_rule_id')->get() as $r) {
                $k = $r->coverage_rule_id ? ($labels[$r->coverage_rule_id]->rule_key ?? 'r' . $r->coverage_rule_id) : 'legacy';
                $byRule[$k] ??= ['label' => $r->coverage_rule_id ? ($labels[$r->coverage_rule_id]->label ?? 'Garantie n° ' . $r->coverage_rule_id) : 'Formules à taux unique', 'count' => 0, 'total' => 0, 'mutual' => 0];
                $byRule[$k]['count'] += (int) $r->n;
                $byRule[$k]['total'] += (int) $r->t;
                $byRule[$k]['mutual'] += (int) $r->m;
            }
            usort($byRule, fn ($a, $b) => $b['mutual'] <=> $a['mutual']);
        }

        // Par prestataire (les 10 premiers).
        $byProv = (clone $validated)->selectRaw('provider_id, count(*) as n, sum(mutual_part_minor) as m')->groupBy('provider_id')->orderByDesc('m')->limit(10)->get();
        $pNames = CareProvider::on('central')->whereIn('id', $byProv->pluck('provider_id'))->pluck('name', 'id');

        return [
            'from' => $from, 'to' => $to, 'scoped' => $scoped,
            'members' => ['total' => $memberIds->count(), 'beneficiaries' => $count['active'] + $count['suspended'] + $dependents, 'dependents' => $dependents] + $count,
            'contributions' => [
                'expected' => $calledMinor, 'paid_on_expected' => $paidOnCalled, 'collected' => $collected, 'overdue' => max(0, $overdue),
                'rate' => $calledMinor > 0 ? round($paidOnCalled * 100 / $calledMinor, 1) : null,
            ],
            'prestations' => [
                'billed' => (int) (clone $all)->where('status', '!=', 'rejected')->sum('total_minor'),
                'covered' => $mutual, 'copay' => $copay,
                'validated' => (clone $validated)->count(),
                'pending' => in_array($f['status'] ?? null, [null, 'captured', 'controlled'], true)
                    ? (int) (($f['status'] ?? null) !== 'controlled' ? ($byStatus['captured']->n ?? 0) : 0) + (int) (($f['status'] ?? null) !== 'captured' ? ($byStatus['controlled']->n ?? 0) : 0) : 0,
                'rejected' => in_array($f['status'] ?? null, [null, 'rejected'], true) ? (int) ($byStatus['rejected']->n ?? 0) : 0,
                'loss_ratio' => $collected > 0 ? round($mutual * 100 / $collected, 1) : null,
            ],
            'by_formula' => array_values($byFormula), 'by_rule' => array_values($byRule),
            'by_provider' => $byProv->map(fn ($r) => ['label' => $pNames[$r->provider_id] ?? 'Établissement n° ' . $r->provider_id, 'count' => (int) $r->n, 'mutual' => (int) $r->m])->all(),
            'pharmacy' => $this->pharmacy($fd, $td, $scoped ? $memberIds : null, $f['provider_id'] ?? null),
            'limits' => $this->limits((int) $to->year, $versionIds, $scoped ? $memberIds : null),
            'monthly' => $this->monthly($from, $to, $validated, $sched),
        ];
    }

    /** Consommation pharmaceutique : délivrances, montants, part non prise en charge, médicaments les plus délivrés. */
    private function pharmacy(string $fd, string $td, $memberIds, ?int $providerId): array
    {
        if (! Schema::hasTable('dispensations')) {
            return ['count' => 0, 'total' => 0, 'excluded' => 0, 'mutual' => 0, 'top' => []];
        }
        $d = Dispensation::whereBetween('dispensed_on', [$fd, $td])
            ->whereHas('prestation', fn ($p) => $p->where('status', '!=', 'rejected'))
            ->when($memberIds, fn ($q) => $q->whereHas('prescription', fn ($r) => $r->whereIn('member_id', $memberIds)))
            ->when($providerId, fn ($q, $p) => $q->where('provider_id', $p));
        $top = DispensationLine::whereIn('dispensation_id', (clone $d)->select('id'))
            ->selectRaw('medicine_id, sum(quantity_dispensed) as q, sum(amount_minor) as a')->groupBy('medicine_id')->orderByDesc('a')->limit(8)->get();
        $names = Medicine::withTrashed()->whereIn('id', $top->pluck('medicine_id'))->pluck('label', 'id');

        return [
            'count' => (clone $d)->count(), 'total' => (int) (clone $d)->sum('total_minor'), 'excluded' => (int) (clone $d)->sum('excluded_minor'),
            'mutual' => (int) Prestation::where('status', 'validated')->whereIn('id', (clone $d)->select('prestation_id'))->sum('mutual_part_minor'),
            'top' => $top->map(fn ($r) => ['label' => $names[$r->medicine_id] ?? 'Médicament n° ' . $r->medicine_id, 'qty' => (int) $r->q, 'amount' => (int) $r->a])->all(),
        ];
    }

    /**
     * Plafonds consommés (formules paramétrées) : pour chaque plafond ANNUEL en montant des versions en
     * cours, nombre de bénéficiaires (ou de familles) à ≥ 80 % et à 100 % sur l'année, en une requête
     * groupée par plafond.
     */
    private function limits(int $year, $versionIds, $memberIds): array
    {
        if (! Schema::hasTable('coverage_limits')) {
            return [];
        }
        $out = [];
        $current = GuaranteeVersion::whereNull('valid_to')->when($versionIds, fn ($q) => $q->whereIn('id', $versionIds))->with('guarantee')->get()->keyBy('id');
        foreach (CoverageLimit::whereIn('guarantee_version_id', $current->keys())->where('active', true)->where('scope', 'year')->whereNotNull('amount_minor')->get() as $l) {
            $v = $current[$l->guarantee_version_id];
            $versionIdsOfG = GuaranteeVersion::where('guarantee_id', $v->guarantee_id)->pluck('id');
            // Parts consommées par recours (une requête groupée), puis regroupement par titulaire du plafond.
            $rows = Prestation::query()->whereIn('status', CoverageEngine::CONSUMING)->whereYear('care_date', $year)
                ->when($l->rule_keys, fn ($w) => $w->whereIn('coverage_rule_id', CoverageRule::whereIn('rule_key', $l->rule_keys)->whereIn('guarantee_version_id', $versionIdsOfG)->select('id')),
                    fn ($w) => $w->whereIn('guarantee_version_id', $versionIdsOfG))
                ->when($memberIds, fn ($w) => $w->whereIn('care_claim_id', CareClaim::whereIn('member_id', $memberIds)->select('id')))
                ->selectRaw('care_claim_id, sum(mutual_part_minor) as m')->groupBy('care_claim_id')->pluck('m', 'care_claim_id');
            $claims = CareClaim::whereIn('id', $rows->keys())->get(['id', 'member_id', 'dependent_id'])->keyBy('id');
            $sums = [];
            foreach ($rows as $claimId => $m) {
                $c = $claims[$claimId] ?? null;
                if (! $c) {
                    continue;
                }
                $holder = $l->holder === 'family' ? 'm' . $c->member_id : 'm' . $c->member_id . '-d' . ($c->dependent_id ?? 0);
                $sums[$holder] = ($sums[$holder] ?? 0) + (int) $m;
            }
            $amount = (int) $l->amount_minor;
            $out[] = [
                'label' => $l->label ?: 'Plafond annuel', 'formula' => $v->guarantee?->name, 'amount' => $amount,
                'holder' => $l->holder === 'family' ? 'familles' : 'bénéficiaires',
                'holders' => count($sums), 'over80' => count(array_filter($sums, fn ($s) => $s >= 0.8 * $amount)),
                'full' => count(array_filter($sums, fn ($s) => $s >= $amount)), 'consumed' => array_sum($sums),
            ];
        }

        return $out;
    }

    /** Évolution mensuelle filtrée : prestations validées (part mutuelle) et cotisations encaissées. */
    private function monthly(Carbon $from, Carbon $to, $validated, $sched): array
    {
        $start = $from->copy()->startOfMonth();
        if ($start->diffInMonths($to->copy()->startOfMonth()) >= ActivityReportService::MAX_MONTHS) {
            $start = $to->copy()->startOfMonth()->subMonthsNoOverflow(ActivityReportService::MAX_MONTHS - 1);
        }
        $g = DB::connection()->getQueryGrammar();
        $ym = fn (string $col) => 'date_format(' . $g->wrap($col) . ", '%Y-%m')";
        $mutual = (clone $validated)->where('care_date', '>=', $start->toDateString())
            ->selectRaw($ym('care_date') . ' as ym, sum(' . $g->wrap('mutual_part_minor') . ') as v')->groupByRaw($ym('care_date'))->pluck('v', 'ym');
        $collected = ContributionPayment::whereBetween('paid_on', [$start->toDateString(), $to->toDateString()])->whereIn('schedule_id', (clone $sched)->select('id'))
            ->selectRaw($ym('paid_on') . ' as ym, sum(' . $g->wrap('amount_minor') . ') as v')->groupByRaw($ym('paid_on'))->pluck('v', 'ym');
        $months = [];
        for ($m = $start->copy(); $m->lte($to); $m->addMonthNoOverflow()) {
            $k = $m->format('Y-m');
            $months[$k] = ['label' => ucfirst($m->locale('fr')->translatedFormat('M Y')), 'collected' => (int) ($collected[$k] ?? 0), 'mutual' => (int) ($mutual[$k] ?? 0)];
        }

        return $months;
    }
}
