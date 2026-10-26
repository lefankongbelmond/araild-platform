<?php

namespace App\Services;

use App\Models\Central\DrTest;

/**
 * Evaluates disaster-recovery drills against their objectives (CDC Phase 2, §28).
 *
 * An objective is "met" when an actual value is recorded and does not exceed the
 * target (lower is better for both RTO and RPO). The derived verdict combines the
 * operator's stated outcome with whether both objectives were met, so a drill that
 * "succeeded" but blew its RTO is surfaced as degraded rather than clean.
 */
class DrAssessmentService
{
    /** True only when both values are present and actual ≤ target. */
    public function objectiveMet(?int $target, ?int $actual): bool
    {
        return $target !== null && $actual !== null && $actual <= $target;
    }

    public function rtoMet(DrTest $test): bool
    {
        return $this->objectiveMet($test->rto_target_minutes, $test->rto_actual_minutes);
    }

    public function rpoMet(DrTest $test): bool
    {
        return $this->objectiveMet($test->rpo_target_minutes, $test->rpo_actual_minutes);
    }

    /**
     * Derived verdict: 'clean' | 'degraded' | 'failed' | 'incomplete'.
     *  - failed      : operator marked the drill failed;
     *  - incomplete  : an objective has a target but no measured actual;
     *  - degraded    : ran but at least one objective was missed;
     *  - clean       : succeeded and every set objective was met.
     */
    public function verdict(DrTest $test): string
    {
        if ($test->outcome === 'failed') {
            return 'failed';
        }

        $rtoSet = $test->rto_target_minutes !== null;
        $rpoSet = $test->rpo_target_minutes !== null;

        if (($rtoSet && $test->rto_actual_minutes === null) || ($rpoSet && $test->rpo_actual_minutes === null)) {
            return 'incomplete';
        }

        $missed = ($rtoSet && ! $this->rtoMet($test)) || ($rpoSet && ! $this->rpoMet($test));
        if ($missed || $test->outcome === 'partial') {
            return 'degraded';
        }

        return 'clean';
    }
}
