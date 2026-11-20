<?php

namespace App\Support;

use App\Models\Tenant\Dependent;
use App\Models\Tenant\Member;

/**
 * Statut lisible des droits, affiché au prestataire (Portail V2, phase 3, 2026-11-17).
 * Calculé à partir des motifs de RightsVerificationService (source unique des contrôles) et de
 * la situation de l'adhérent ; il ne remplace aucun contrôle.
 */
final class RightsStatus
{
    /** clé => [libellé, ton] */
    public const LABELS = [
        'active'    => ['DROITS ACTIFS', 'ok'],
        'network'   => ['DROITS ACTIFS — ÉTABLISSEMENT HORS RÉSEAU DE LA GARANTIE', 'warn'],
        'pending'   => ['DROITS EN ATTENTE DE CONFIRMATION', 'warn'],
        'suspended' => ['DROITS SUSPENDUS', 'ko'],
        'expired'   => ['DROITS EXPIRÉS', 'ko'],
        'unknown'   => ['BÉNÉFICIAIRE NON IDENTIFIÉ', 'ko'],
    ];

    public const HINTS = [
        'active'    => 'Les soins peuvent être pris en charge selon la garantie ci-dessous.',
        'network'   => 'L’adhérent a des droits, mais sa garantie ne couvre pas les soins faits dans votre établissement. Orientez-le vers la mutuelle.',
        'pending'   => 'Adhésion pas encore validée par la mutuelle, ou période d’observation en cours : aucune prise en charge pour l’instant.',
        'suspended' => 'Adhérent suspendu ou cotisations en retard : aucune prise en charge tant que la situation n’est pas régularisée auprès de la mutuelle.',
        'expired'   => 'Adhésion terminée, ayant droit retiré ou aucune garantie en cours : aucune prise en charge.',
        'unknown'   => 'Aucun bénéficiaire ne correspond. Vérifiez le code ou la carte, ou orientez la personne vers la mutuelle.',
    ];

    /** @param array<string> $reasons */
    public static function of(array $reasons, ?Member $member, ?Dependent $dependent = null, bool $pendingSubscription = false): string
    {
        if (! $member || array_intersect($reasons, ['unknown_member', 'invalid_card'])) {
            return 'unknown';
        }
        $status = $member->status->value ?? (string) $member->status;
        if (in_array($status, ['left', 'revoked'], true) || ($dependent && $dependent->status !== 'active') || in_array('dependent_removed', $reasons, true)) {
            return 'expired';
        }
        if ($status === 'suspended') {
            return 'suspended';
        }
        if (in_array('no_active_guarantee', $reasons, true)) {
            return $pendingSubscription ? 'pending' : 'expired';
        }
        if (in_array('contribution_arrears', $reasons, true) || in_array('member_inactive', $reasons, true)) {
            return 'suspended';
        }
        if (in_array('observation_period', $reasons, true)) {
            return 'pending';
        }
        if (in_array('provider_not_authorised', $reasons, true)) {
            return 'network';
        }

        return $reasons ? 'suspended' : 'active';
    }

    public static function label(string $key): string
    {
        return isset(self::LABELS[$key][0]) ? __(self::LABELS[$key][0]) : $key;
    }

    public static function tone(string $key): string
    {
        return self::LABELS[$key][1] ?? 'ko';
    }
}
