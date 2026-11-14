<?php

namespace App\Services;

use App\Models\Tenant\Ceiling;
use App\Models\Tenant\GuaranteeVersion;
use App\Models\Tenant\Prestation;

/**
 * Computes the mutual/beneficiary split for a care prestation (CDC §14).
 *
 * Base split comes from the guarantee version's coverage rate. The mutual's
 * share is then capped by any applicable ceiling:
 *   - per_act : the mutual pays at most `ceiling_minor` for this single act;
 *   - annual  : the mutual pays at most `ceiling_minor` MINUS what it has already
 *               granted this member (for this act, or globally) in the same year.
 * Whatever the ceiling doesn't cover falls back onto the beneficiary.
 */
class ClaimAssessmentService
{
    /**
     * @return array{mutual_minor:int, beneficiary_minor:int, capped:bool, reason:?string}
     */
    public function assess(GuaranteeVersion $version, int $actTypeId, int $totalMinor, int $memberId, string $careDate): array
    {
        $d = $this->detail($version, $actTypeId, $totalMinor, $memberId, $careDate);

        return [
            'mutual_minor'      => $d['mutual_minor'],
            'beneficiary_minor' => $d['beneficiary_minor'],
            'capped'            => $d['capped'],
            'reason'            => $d['reason'],
        ];
    }

    /**
     * Même calcul que assess(), avec le détail utile à l'estimation avant soins (2026-11-17) :
     * part théorique au taux de la garantie, plafond appliqué et reste disponible AVANT ces soins.
     * Aucune écriture : sert aussi à la simulation du portail prestataire.
     *
     * @return array{base_minor:int, mutual_minor:int, beneficiary_minor:int, capped:bool, reason:?string,
     *               ceiling:?Ceiling, remaining_before:?int}
     */
    public function detail(GuaranteeVersion $version, int $actTypeId, int $totalMinor, int $memberId, string $careDate): array
    {
        // 1) Base split from the coverage rate (mutual's percentage share).
        $base = (int) round($totalMinor * ((float) $version->coverage_rate) / 100);
        $mutual = $base;
        $capped = false;
        $reason = null;
        $remaining = null;

        // 2) Ceiling: prefer an act-specific ceiling, else the version's global one.
        $ceiling = $this->ceilingFor($version, $actTypeId);

        if ($ceiling) {
            $remaining = $this->remainingCeiling($ceiling, $version->id, $actTypeId, $memberId, $careDate);
            if ($mutual > $remaining) {
                $mutual = max(0, $remaining);
                $capped = true;
                $reason = $ceiling->period; // 'annual' | 'per_act' | 'per_stay'
            }
        }

        return [
            'base_minor'        => $base,
            'mutual_minor'      => $mutual,
            'beneficiary_minor' => $totalMinor - $mutual,
            'capped'            => $capped,
            'reason'            => $reason,
            'ceiling'           => $ceiling,
            'remaining_before'  => $remaining,
        ];
    }

    /** Plafond applicable à un acte : celui de l'acte, sinon le plafond global de la version. */
    public function ceilingFor(GuaranteeVersion $version, int $actTypeId): ?Ceiling
    {
        return Ceiling::where('guarantee_version_id', $version->id)->where('act_type_id', $actTypeId)->first()
            ?? Ceiling::where('guarantee_version_id', $version->id)->whereNull('act_type_id')->first();
    }

    /** Reste disponible sous un plafond pour un adhérent (public : affiché à la vérification des droits). */
    public function remaining(Ceiling $ceiling, int $versionId, int $actTypeId, int $memberId, string $careDate): int
    {
        return $this->remainingCeiling($ceiling, $versionId, $actTypeId, $memberId, $careDate);
    }

    /** Remaining headroom under the ceiling for this member/act (minor units). */
    private function remainingCeiling(Ceiling $ceiling, int $versionId, int $actTypeId, int $memberId, string $careDate): int
    {
        if ($ceiling->period !== 'annual') {
            return (int) $ceiling->ceiling_minor;   // per_act / per_stay: full ceiling each time
        }

        // Annual: subtract mutual parts already granted this calendar year — validated ones AND
        // those still awaiting validation (captured / controlled). Since 2026-11-15 pending parts are
        // reserved: two declarations close together can no longer both use the same remaining
        // ceiling. A rejected prestation releases its reservation.
        $year = (int) substr($careDate, 0, 4);

        $alreadyGranted = Prestation::query()
            ->whereIn('status', ['validated', 'captured', 'controlled'])
            ->whereYear('care_date', $year)
            ->when($ceiling->act_type_id, fn ($q) => $q->where('act_type_id', $actTypeId))
            ->whereHas('careClaim', fn ($q) => $q->where('member_id', $memberId))
            ->sum('mutual_part_minor');

        return max(0, (int) $ceiling->ceiling_minor - (int) $alreadyGranted);
    }
}
