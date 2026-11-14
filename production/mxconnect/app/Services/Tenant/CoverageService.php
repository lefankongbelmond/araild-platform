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
    public function __construct(private ClaimAssessmentService $assessment, private ClaimCaptureService $capture) {}

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

        return [
            'version' => $version,
            'guarantee' => $version?->guarantee?->name,
            'coverage_rate' => $version?->coverage_rate,
            'copay_rate' => $version?->copay_rate,
            'subscription' => $subscription,
            'pending_subscription' => $pending,
            'observation_ends_on' => $subscription?->observation_ends_on,
            'version_valid_to' => $version?->valid_to,
            'overdue_count' => (clone $overdue)->count(),
            'overdue_since' => (clone $overdue)->min('due_date'),
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
    public function estimate(GuaranteeVersion $version, Member $member, int $actTypeId, int $totalMinor, string $date): array
    {
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
}
