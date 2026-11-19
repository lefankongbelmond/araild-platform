<?php

namespace App\Services\Tenant;

use App\Models\Tenant\ActType;
use App\Models\Tenant\Ceiling;
use App\Models\Tenant\ContributionSchedule;
use App\Models\Tenant\Dependent;
use App\Models\Tenant\GuaranteeVersion;
use App\Models\Tenant\Member;
use App\Models\Tenant\Prestation;
use App\Models\Tenant\RightsCheck;
use App\Services\ClaimAssessmentService;
use App\Services\ClaimCaptureService;
use Illuminate\Support\Facades\Schema;

/**
 * Situation mutualiste et estimation de couverture, pour le portail prestataire
 * (Portail V2, phase 3, 2026-11-17). Toutes les valeurs viennent des paramètres de la garantie
 * en vigueur (taux, ticket modérateur, plafonds, exclusions, réseau) et des cotisations : aucun
 * taux, tarif ou plafond n'est inventé. Ce qui n'est pas défini est affiché « non défini ».
 * Aucune donnée médicale : ni diagnostic, ni détail des soins faits ailleurs.
 */
class CoverageService
{
    public function __construct(private ClaimAssessmentService $assessment, private ClaimCaptureService $capture, private CoverageEngine $engine) {}

    public function situation(Member $member, ?Dependent $dependent, int $providerId): array
    {
        $today = now()->toDateString();
        $version = $this->capture->versionFor($member, $today);
        $subscription = $member->subscriptions()->where('status', 'validated')->latest('effective_date')->first();
        $pending = ! $subscription && $member->subscriptions()->where('status', 'captured')->exists();

        // Cotisations (échéances régulières, hors droit d'adhésion pour la « date de validité »).
        $subIds = $member->subscriptions()->where('status', 'validated')->pluck('id');
        $hasKind = Schema::hasColumn('contribution_schedules', 'kind');
        $overdue = ContributionSchedule::whereIn('subscription_id', $subIds)
            ->where(fn ($w) => $w->where('status', 'overdue')->orWhere(fn ($x) => $x->where('status', 'to_pay')->whereDate('due_date', '<', $today)));
        $paidThrough = ContributionSchedule::whereIn('subscription_id', $subIds)->where('status', 'paid')
            ->when($hasKind, fn ($q) => $q->where(fn ($w) => $w->whereNull('kind')->orWhere('kind', '!=', 'membership_fee')))
            ->orderByDesc('due_date')->first();

        $ceilings = [];
        if ($version) {
            foreach (Ceiling::with('actType')->where('guarantee_version_id', $version->id)->orderByRaw('act_type_id is not null')->get() as $c) {
                $actId = $c->act_type_id ?? 0;
                $ceilings[] = [
                    'label' => $c->actType?->label ?? 'Tous actes',
                    'period' => Ceiling::PERIODS[$c->period] ?? $c->period,
                    'amount' => (int) $c->ceiling_minor,
                    // Reste : seulement pour un plafond annuel (les autres se renouvellent à chaque acte / séjour).
                    'remaining' => $c->period === 'annual' ? $this->assessment->remaining($c, $version->id, (int) $actId, $member->id, $today) : null,
                ];
            }
        }

        // Formule paramétrée (2026-11-18) : garanties détaillées, carences et plafonds du moteur.
        $catalog = $version && $version->hasCatalog() ? $this->catalogSituation($version, $member, $dependent, $subscription, $today) : null;
        if ($catalog) {
            $ceilings = $catalog['limits'];
        }

        return [
            'version' => $version,
            'catalog' => $catalog,
            'guarantee' => $version?->guarantee?->name,
            'coverage_rate' => $version?->coverage_rate,
            'copay_rate' => $version?->copay_rate,
            'subscription' => $subscription,
            'pending_subscription' => $pending,
            'observation_ends_on' => $catalog ? null : $subscription?->observation_ends_on,
            'version_valid_to' => $version?->valid_to,
            'overdue_count' => (clone $overdue)->count(),
            'overdue_since' => (clone $overdue)->min('due_date'),
            'grace_until' => \App\Support\ArrearsPolicy::graceEnd((clone $overdue)->min('due_date')),
            'paid_through' => $paidThrough ? ['label' => $paidThrough->label(), 'due_date' => $paidThrough->due_date] : null,
            'ceilings' => $ceilings,
            'exclusions' => $version && is_array($version->exclusions) && $version->exclusions
                ? ActType::withTrashed()->whereIn('id', $version->exclusions)->orderBy('label')->pluck('label')->all() : [],
            'in_network' => $version ? (! is_array($version->provider_network) || in_array($providerId, $version->provider_network, true)) : null,
            'network_open' => $version && ! is_array($version->provider_network),
        ];
    }

    /** Vérification précédente du même bénéficiaire par le même établissement. */
    public function lastCheck(RightsCheck $current): ?RightsCheck
    {
        return RightsCheck::where('care_provider_id', $current->care_provider_id)->where('member_id', $current->member_id)
            ->when(RightsCheck::hasIdentificationColumns(), fn ($q) => $current->dependent_id
                ? $q->where('dependent_id', $current->dependent_id) : $q->whereNull('dependent_id'))
            ->where('id', '<', $current->id)->latest('id')->first();
    }

    /** Prises en charge de CET établissement pour ce bénéficiaire (12 derniers mois) — jamais celles des autres. */
    public function history(Member $member, ?Dependent $dependent, int $providerId)
    {
        return Prestation::with('actType')->where('provider_id', $providerId)
            ->whereDate('care_date', '>=', now()->subYear()->toDateString())
            ->whereHas('careClaim', fn ($q) => $q->where('member_id', $member->id)
                ->when($dependent, fn ($w) => $w->where('dependent_id', $dependent->id), fn ($w) => $w->whereNull('dependent_id')))
            ->latest('care_date')->limit(10)->get();
    }

    /**
     * Estimation AVANT soins (rien n'est enregistré) : part mutuelle, part patient, part non couverte
     * (acte exclu ou au-delà du plafond), plafond restant, comparaison au tarif conventionnel.
     */
    public function estimate(GuaranteeVersion $version, Member $member, int $actTypeId, int $totalMinor, string $date, ?Dependent $dependent = null, ?int $providerId = null, array $opt = []): array
    {
        if ($version->hasCatalog()) {
            return $this->engineEstimate($member, $dependent, $actTypeId, $totalMinor, $date, $providerId, $opt);
        }
        $act = ActType::find($actTypeId);
        $tariff = Schema::hasColumn('act_types', 'tariff_minor') ? $act?->tariff_minor : null;
        $excluded = is_array($version->exclusions) && in_array($actTypeId, $version->exclusions, true);

        if ($excluded) {
            return ['act' => $act, 'total' => $totalMinor, 'excluded' => true, 'base' => 0, 'mutual' => 0, 'patient' => $totalMinor,
                'copay' => 0, 'over_ceiling' => 0, 'non_covered' => $totalMinor, 'ceiling' => null, 'remaining_before' => null,
                'remaining_after' => null, 'tariff' => $tariff, 'above_tariff' => $tariff !== null && $totalMinor > $tariff,
                'rate' => $version->coverage_rate, 'copay_rate' => $version->copay_rate];
        }

        $d = $this->assessment->detail($version, $actTypeId, $totalMinor, $member->id, $date);
        $overCeiling = $d['base_minor'] - $d['mutual_minor'];

        return [
            'act' => $act, 'total' => $totalMinor, 'excluded' => false,
            'base' => $d['base_minor'], 'mutual' => $d['mutual_minor'], 'patient' => $d['beneficiary_minor'],
            'copay' => $totalMinor - $d['base_minor'],                 // part hors taux de couverture
            'over_ceiling' => $overCeiling,                           // au-delà du plafond : non couvert
            'non_covered' => $overCeiling,
            'ceiling' => $d['ceiling'], 'remaining_before' => $d['remaining_before'],
            'remaining_after' => $d['remaining_before'] !== null && $d['ceiling']?->period === 'annual' ? max(0, $d['remaining_before'] - $d['mutual_minor']) : null,
            'tariff' => $tariff, 'above_tariff' => $tariff !== null && $tariff > 0 && $totalMinor > $tariff,
            'rate' => $version->coverage_rate, 'copay_rate' => $version->copay_rate,
        ];
    }

    /** Estimation par le moteur (formule paramétrée) : mêmes clés que l'estimation classique, plus le statut et les plafonds. */
    private function engineEstimate(Member $member, ?Dependent $dependent, int $actTypeId, int $totalMinor, string $date, ?int $providerId, array $opt): array
    {
        $act = ActType::find($actTypeId);
        $e = $this->engine->evaluate($member, $dependent, $actTypeId, $totalMinor, $date, $providerId, $opt);
        $tariff = $act?->tariff_minor;
        $amountLimit = collect($e['limits'])->first(fn ($l) => $l['remaining_before'] !== null);

        return [
            'engine' => true, 'status' => $e['status'], 'label' => $e['label'], 'reasons' => $e['reasons'], 'ok' => $e['ok'],
            'act' => $act, 'total' => $totalMinor, 'excluded' => $e['status'] === 'not_covered',
            'base' => $e['mutual'] + $e['over_limit'], 'mutual' => $e['mutual'], 'patient' => $e['patient'],
            'copay' => $e['copay'], 'over_ceiling' => $e['over_limit'], 'non_covered' => $e['non_covered'],
            'eligible' => $e['eligible'], 'ceiling' => null,
            'remaining_before' => $amountLimit['remaining_before'] ?? null, 'remaining_after' => $amountLimit['remaining_after'] ?? null,
            'limits' => $e['limits'], 'waiting_until' => $e['waiting_until'] ?? null,
            'guarantee_label' => $e['rule']?->label,
            'tariff' => $tariff, 'above_tariff' => $tariff !== null && $tariff > 0 && $totalMinor > $tariff,
            'rate' => $e['rate'], 'copay_rate' => $e['copay_rate'], 'exception' => (bool) $e['exception'],
        ];
    }

    /** Garanties, carences en cours et plafonds (avec le reste) d'une formule paramétrée, pour CE bénéficiaire. */
    public function catalogSituation(GuaranteeVersion $version, Member $member, ?Dependent $dependent, $subscription, string $today): array
    {
        $cats = $this->engine->beneficiaryCategories($member, $dependent, $subscription, $version, $today);
        $start = $subscription ? $this->engine->coverageStart($member, $dependent, $subscription, $version) : null;
        $rules = [];
        $seen = [];
        $active = $version->rules()->with('category')->where('active', true)->get();
        foreach ($active->pluck('service_category_id')->unique() as $catId) {
            // La garantie qui s'appliquerait vraiment à CE bénéficiaire pour cette catégorie de soins.
            $r = $this->engine->ruleFor($version, $catId ? (new ActType)->forceFill(['service_category_id' => $catId]) : null, $cats);
            if (! $r || isset($seen[$r->id])) {
                continue;
            }
            $seen[$r->id] = true;
            $days = $this->engine->waitingDays($version, $r, $cats);
            $until = $start && $days > 0 ? $start->copy()->addDays($days) : null;
            $rules[] = [
                'label' => $r->label, 'category' => $r->category?->label ?? 'Toutes prestations',
                'rate' => (float) $r->coverage_rate, 'copay_rate' => $r->copay_rate,
                'waiting_until' => $until && $until->toDateString() > $today ? $until->toDateString() : null,
                'emergency_exempt' => $r->emergency_exempt, 'tariff_cap' => $r->tariff_cap,
            ];
        }
        $limits = [];
        foreach ($version->limits()->where('active', true)->orderBy('id')->get() as $l) {
            $used = $l->scope === 'act' ? [0, 0] : $this->engine->consumed($l, $member, $dependent, $today, $version);
            $limits[] = [
                'label' => $l->label ?: 'Plafond',
                'period' => (\App\Models\Tenant\CoverageLimit::SCOPES[$l->scope] ?? $l->scope) . ' · ' . (\App\Models\Tenant\CoverageLimit::HOLDERS[$l->holder] ?? $l->holder),
                'amount' => $l->amount_minor, 'max_count' => $l->max_count,
                'remaining' => $l->scope === 'act' || $l->amount_minor === null ? null : max(0, $l->amount_minor - $used[0]),
                'remaining_count' => $l->scope === 'act' || $l->max_count === null ? null : max(0, $l->max_count - $used[1]),
            ];
        }

        return ['rules' => $rules, 'limits' => $limits, 'categories' => $cats];
    }
}
