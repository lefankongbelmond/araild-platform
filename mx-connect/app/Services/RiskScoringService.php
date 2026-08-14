<?php

namespace App\Services;

/**
 * Risk scoring (CDC Phase 2, §22). Score = likelihood × impact (1..25), banded
 * into low / medium / high / critical by the thresholds in config. Keeping the
 * banding here (not in the model) makes the matrix configurable per deployment.
 */
class RiskScoringService
{
    public function score(int $likelihood, int $impact): int
    {
        return $likelihood * $impact;
    }

    public function band(int $score): string
    {
        $b = config('mxconnect.governance.risk_bands');
        return match (true) {
            $score <= $b['low']    => 'low',
            $score <= $b['medium'] => 'medium',
            $score <= $b['high']   => 'high',
            default                => 'critical',
        };
    }

    /** Tailwind tone for a band, for the register heat display. */
    public function tone(string $band): string
    {
        return [
            'low'      => 'bg-sage/15 text-sage',
            'medium'   => 'bg-mist text-ink',
            'high'     => 'bg-clay/15 text-clay',
            'critical' => 'bg-clay/25 text-clay',
        ][$band] ?? 'bg-mist';
    }
}
