<?php

namespace App\Services;

use App\Models\Tenant\CareClaim;
use App\Models\Tenant\GuaranteeVersion;
use App\Models\Tenant\Member;
use App\Models\Tenant\Prestation;
use App\Models\Tenant\User;
use App\Services\Tenant\CoverageEngine;
use Illuminate\Support\Facades\DB;

/**
 * Saisie d'une prestation (« maker ») — commune au back-office (gestionnaire des
 * prestations) et au portail des prestataires : vérification des droits, calcul de la
 * part mutuelle / part adhérent, contrôle anti-fraude. La validation reste une étape
 * distincte, faite par une AUTRE personne (contrôleur-validateur ou administrateur).
 */
class ClaimCaptureService
{
    public function __construct(
        private ClaimAssessmentService $assessment,
        private FraudDetectionService $fraud,
        private RightsVerificationService $rights,
        private CoverageEngine $engine,
    ) {}

    /** Version de garantie applicable à l'adhérent à une date (souscription validée), ou null. */
    public function versionFor(Member $member, string $careDate): ?GuaranteeVersion
    {
        $subscription = $member->subscriptions()->where('status', 'validated')->latest('effective_date')->first();

        return $subscription ? GuaranteeVersion::applicableOn($subscription->guaranteeVersion->guarantee_id, $careDate) : null;
    }

    /**
     * @param array{act_type_id:int, provider_id:int, care_date:string, total_minor:int, emergency?:bool, accident?:bool} $data
     * @return array{ok:bool, reasons:array<string>, prestation:?Prestation, alerts:array, status?:string}
     */
    public function capture(CareClaim $claim, array $data, User $by, string $source = 'mutual'): array
    {
        $member = $claim->member;
        $version = $this->versionFor($member, $data['care_date']);
        if (! $version) {
            return ['ok' => false, 'reasons' => ['no_active_guarantee'], 'prestation' => null, 'alerts' => []];
        }

        // Formule paramétrée (garanties détaillées) : moteur de prise en charge (2026-11-18).
        if ($version->hasCatalog()) {
            return $this->captureWithEngine($claim, $data, $by, $source);
        }

        // Contrôle des droits : un échec bloque la saisie (avec ses motifs).
        $verdict = $this->rights->verify($member, $version->guarantee_id, $data['care_date'], (int) $data['act_type_id'], (int) $data['provider_id']);
        if (! $verdict['ok']) {
            return ['ok' => false, 'reasons' => $verdict['reasons'], 'prestation' => null, 'alerts' => []];
        }

        // Partage mutuelle / adhérent (plafonné).
        // Lignes non prises en charge (pharmacie : médicament exclu ou hors liste, 2026-11-19) : hors base de calcul.
        $excluded = min((int) ($data['excluded_minor'] ?? 0), (int) $data['total_minor']);
        $split = $this->assessment->assess($version, (int) $data['act_type_id'], (int) $data['total_minor'] - $excluded, $member->id, $data['care_date']);
        $split['beneficiary_minor'] = (int) $data['total_minor'] - $split['mutual_minor'];

        $prestation = $claim->prestations()->create([
            'act_type_id'            => $data['act_type_id'],
            'provider_id'            => $data['provider_id'],
            'guarantee_version_id'   => $version->id,
            'care_date'              => $data['care_date'],
            'total_minor'            => $data['total_minor'],
            'mutual_part_minor'      => $split['mutual_minor'],
            'beneficiary_part_minor' => $split['beneficiary_minor'],
            'status'                 => 'captured',
        ] + (Prestation::hasTraceColumns() ? ['captured_by' => $by->id, 'source' => $source] : []));

        // Une version de garantie utilisée par un recours devient immuable.
        $version->update(['locked' => true]);

        // Contrôle anti-fraude (non bloquant) : écrit des alertes.
        $alerts = $this->fraud->screen($prestation, $member->id, $split['capped']);

        return ['ok' => true, 'reasons' => [], 'prestation' => $prestation, 'alerts' => $alerts];
    }

    /**
     * Saisie par le moteur : statut, carence, plafonds partagés, dérogations. Tout se passe dans UNE
     * transaction, après verrouillage de la ligne de l'adhérent : deux saisies simultanées pour la
     * même famille sont traitées l'une après l'autre et ne consomment jamais deux fois le même reste.
     */
    private function captureWithEngine(CareClaim $claim, array $data, User $by, string $source): array
    {
        return DB::transaction(function () use ($claim, $data, $by, $source) {
            $member = Member::whereKey($claim->member_id)->lockForUpdate()->firstOrFail();
            $dependent = $claim->dependent_id ? $claim->dependent()->first() : null;
            $eval = $this->engine->evaluate($member, $dependent, (int) $data['act_type_id'], (int) $data['total_minor'], $data['care_date'],
                (int) $data['provider_id'], ['emergency' => (bool) ($data['emergency'] ?? false), 'accident' => (bool) ($data['accident'] ?? false),
                    'excluded_minor' => (int) ($data['excluded_minor'] ?? 0), 'preauthorization_id' => $data['preauthorization_id'] ?? null]);
            if (! $eval['ok']) {
                return ['ok' => false, 'reasons' => $eval['reasons'], 'prestation' => null, 'alerts' => [], 'status' => $eval['status'],
                    'waiting_until' => $eval['waiting_until'] ?? null, 'evaluation' => $eval];
            }
            $version = $eval['version'];
            $exception = $eval['exception'];

            $prestation = $claim->prestations()->create([
                'act_type_id'            => $data['act_type_id'],
                'provider_id'            => $data['provider_id'],
                'guarantee_version_id'   => $version->id,
                'care_date'              => $data['care_date'],
                'total_minor'            => $data['total_minor'],
                'mutual_part_minor'      => $eval['mutual'],
                'beneficiary_part_minor' => $eval['patient'],
                'status'                 => 'captured',
                'coverage_rule_id'       => $eval['rule']->id,
                'eligible_minor'         => $eval['eligible'],
                'copay_minor'            => $eval['copay'],
                'non_covered_minor'      => $eval['non_covered'],
                'emergency'              => (bool) ($data['emergency'] ?? false),
                'accident'               => (bool) ($data['accident'] ?? false),
                'coverage_exception_id'  => $exception?->id,
                'exception_used_minor'   => $eval['exception_extra_used'],
            ] + (\Illuminate\Support\Facades\Schema::hasColumn('prestations', 'preauthorization_id') ? [
                'preauthorization_id'    => $eval['authorization']?->id,
                'authorization_missing'  => (bool) $eval['authorization_missing'],
            ] : []) + (Prestation::hasTraceColumns() ? ['captured_by' => $by->id, 'source' => $source] : []));

            // Accord préalable consommé par ces soins (un accord ne sert qu'une fois).
            if ($eval['authorization']) {
                \App\Models\Tenant\Preauthorization::whereKey($eval['authorization']->id)->lockForUpdate()->first()
                    ?->update(['status' => 'used', 'prestation_id' => $prestation->id]);
            }
            // URGENCE soignée sans accord : jamais bloquée ; une demande de régularisation est ouverte.
            if ($eval['authorization_missing']) {
                app(\App\Services\Tenant\PreauthorizationService::class)->openEmergencyRegularisation($prestation, $claim, $eval, $by, $source);
                $this->fraud->signal($prestation, 'financial', 'watch', 'emergency_without_authorization');
            }
            if ($exception && $eval['exception_extra_used'] > 0) {
                $exception->increment('used_minor', $eval['exception_extra_used']);
            }
            $version->update(['locked' => true]);

            $alerts = $this->fraud->screen($prestation, $member->id, $eval['over_limit'] > 0);

            return ['ok' => true, 'reasons' => [], 'prestation' => $prestation, 'alerts' => $alerts, 'evaluation' => $eval,
                'authorization_missing' => (bool) $eval['authorization_missing']];
        });
    }

    /** Prestation rejetée : la part prise sur une dérogation « plafond supplémentaire » est rendue. */
    public function releaseException(Prestation $prestation): void
    {
        $used = (int) ($prestation->exception_used_minor ?? 0);
        if ($used > 0 && $prestation->coverage_exception_id) {
            \App\Models\Tenant\CoverageException::whereKey($prestation->coverage_exception_id)
                ->where('used_minor', '>=', $used)->decrement('used_minor', $used);
        }
        // Accord préalable rendu utilisable ; régularisation d'urgence sans objet (2026-11-19).
        if (\Illuminate\Support\Facades\Schema::hasTable('preauthorizations')) {
            \App\Models\Tenant\Preauthorization::where('prestation_id', $prestation->id)->where('status', 'used')
                ->update(['status' => 'approved', 'prestation_id' => null]);
            \App\Models\Tenant\Preauthorization::where('prestation_id', $prestation->id)->where('status', 'requested')->where('emergency', true)
                ->update(['status' => 'cancelled', 'decision_note' => 'Prestation rejetée : régularisation sans objet.']);
        }
    }
}
