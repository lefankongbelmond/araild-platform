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
        // 1) Base split from the coverage rate (mutual's percentage share).
        $mutual = (int) round($totalMinor * ((float) $version->coverage_rate) / 100);
        $capped = false;
        $reason = null;

        // 2) Ceiling: prefer an act-specific ceiling, else the version's global one.
        $ceiling = Ceiling::where('guarantee_version_id', $version->id)
            ->where('act_type_id', $actTypeId)->first()
            ?? Ceiling::where('guarantee_version_id', $version->id)
                ->whereNull('act_type_id')->first();

        if ($ceiling) {
            $limit = $this->remainingCeiling($ceiling, $version->id, $actTypeId, $memberId, $careDate);
            if ($mutual > $limit) {
                $mutual = max(0, $limit);
                $capped = true;
                $reason = $ceiling->period; // 'annual' | 'per_act' | 'per_stay'
            }
        }

        return [
            'mutual_minor'      => $mutual,
            'beneficiary_minor' => $totalMinor - $mutual,
            'capped'            => $capped,
            'reason'            => $reason,
        ];
    }

    /** Remaining headroom under the ceiling for this member/act (minor units). */
    private function remainingCeiling(Ceiling $ceiling, int $versionId, int $actTypeId, int $memberId, string $careDate): int
    {
        if ($ceiling->period !== 'annual') {
            return (int) $ceiling->ceiling_minor;   // per_act / per_stay: full ceiling each time
        }

        // Annual: subtract mutual parts already granted this calendar year.
        $year = (int) substr($careDate, 0, 4);

        $alreadyGranted = Prestation::query()
            ->where('status', 'validated')
            ->whereYear('care_date', $year)
            ->when($ceiling->act_type_id, fn ($q) => $q->where('act_type_id', $actTypeId))
            ->whereHas('careClaim', fn ($q) => $q->where('member_id', $memberId))
            ->sum('mutual_part_minor');

        return max(0, (int) $ceiling->ceiling_minor - (int) $alreadyGranted);
    }
}
