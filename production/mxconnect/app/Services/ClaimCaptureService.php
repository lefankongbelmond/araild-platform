<?php

namespace App\Services;

use App\Models\Tenant\CareClaim;
use App\Models\Tenant\GuaranteeVersion;
use App\Models\Tenant\Member;
use App\Models\Tenant\Prestation;
use App\Models\Tenant\User;

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
    ) {}

    /** Version de garantie applicable à l'adhérent à une date (souscription validée), ou null. */
    public function versionFor(Member $member, string $careDate): ?GuaranteeVersion
    {
        $subscription = $member->subscriptions()->where('status', 'validated')->latest('effective_date')->first();

        return $subscription ? GuaranteeVersion::applicableOn($subscription->guaranteeVersion->guarantee_id, $careDate) : null;
    }

    /**
     * @param array{act_type_id:int, provider_id:int, care_date:string, total_minor:int} $data
     * @return array{ok:bool, reasons:array<string>, prestation:?Prestation, alerts:array}
     */
    public function capture(CareClaim $claim, array $data, User $by, string $source = 'mutual'): array
    {
        $member = $claim->member;
        $version = $this->versionFor($member, $data['care_date']);
        if (! $version) {
            return ['ok' => false, 'reasons' => ['no_active_guarantee'], 'prestation' => null, 'alerts' => []];
        }

        // Contrôle des droits : un échec bloque la saisie (avec ses motifs).
        $verdict = $this->rights->verify($member, $version->guarantee_id, $data['care_date'], (int) $data['act_type_id'], (int) $data['provider_id']);
        if (! $verdict['ok']) {
            return ['ok' => false, 'reasons' => $verdict['reasons'], 'prestation' => null, 'alerts' => []];
        }

        // Partage mutuelle / adhérent (plafonné).
        $split = $this->assessment->assess($version, (int) $data['act_type_id'], (int) $data['total_minor'], $member->id, $data['care_date']);

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
}
