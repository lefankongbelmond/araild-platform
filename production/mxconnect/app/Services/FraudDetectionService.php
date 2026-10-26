<?php

namespace App\Services;

use App\Models\Tenant\Alert;
use App\Models\Tenant\Prestation;
use Illuminate\Support\Carbon;

/**
 * Rule-based anti-fraud screening for prestations (CDC §17).
 *
 * Alerts NEVER block a claim — they surface risk for the fraud officer, who
 * clears or acts on them. Each rule that fires writes an `alerts` row and is
 * returned so the UI can show it immediately. Rules are intentionally simple,
 * explainable and deterministic; scoring/ML is a later phase.
 */
class FraudDetectionService
{
    /** Overconsumption threshold: same act, same member, trailing 30 days. */
    private const OVERCONSUMPTION_WINDOW_DAYS = 30;
    private const OVERCONSUMPTION_COUNT = 3;

    /** @return array<int,array{category:string, level:string, message:string}> */
    public function screen(Prestation $prestation, int $memberId, bool $ceilingCapped): array
    {
        $fired = [];

        // 1) Duplicate: same member + act + care date already recorded (not rejected).
        $dupExists = Prestation::query()
            ->where('id', '!=', $prestation->id)
            ->where('act_type_id', $prestation->act_type_id)
            ->whereDate('care_date', $prestation->care_date)
            ->where('status', '!=', 'rejected')
            ->whereHas('careClaim', fn ($q) => $q->where('member_id', $memberId))
            ->exists();
        if ($dupExists) {
            $fired[] = $this->raise($prestation, 'duplicate', 'critical', 'duplicate_care_event');
        }

        // 2) Temporal: care date in the future, or unusually old (late submission).
        $care = Carbon::parse($prestation->care_date);
        if ($care->isFuture()) {
            $fired[] = $this->raise($prestation, 'temporal', 'watch', 'future_care_date');
        } elseif ($care->lt(now()->subDays(90))) {
            $fired[] = $this->raise($prestation, 'temporal', 'watch', 'late_submission');
        }

        // 3) Financial: the mutual's share was capped by a ceiling.
        if ($ceilingCapped) {
            $fired[] = $this->raise($prestation, 'financial', 'watch', 'ceiling_reached');
        }

        // 4) Overconsumption: too many of the same act in a short window.
        $recent = Prestation::query()
            ->where('id', '!=', $prestation->id)
            ->where('act_type_id', $prestation->act_type_id)
            ->where('status', '!=', 'rejected')
            ->whereDate('care_date', '>=', now()->subDays(self::OVERCONSUMPTION_WINDOW_DAYS)->toDateString())
            ->whereHas('careClaim', fn ($q) => $q->where('member_id', $memberId))
            ->count();
        if ($recent + 1 >= self::OVERCONSUMPTION_COUNT) {
            $fired[] = $this->raise($prestation, 'overconsumption', 'watch', 'frequent_same_act');
        }

        return $fired;
    }

    private function raise(Prestation $prestation, string $category, string $level, string $code): array
    {
        Alert::create([
            'category'    => $category,
            'level'       => $level,
            'object_type' => Prestation::class,
            'object_id'   => $prestation->id,
            'details'     => ['code' => $code],
            'status'      => 'open',
        ]);

        return ['category' => $category, 'level' => $level, 'message' => $code];
    }
}
