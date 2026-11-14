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

        // 5) Montant déclaré supérieur au tarif conventionnel de l'acte (2026-11-17, audit A10) :
        //    signal à examiner, jamais une correction automatique ni un refus. Seulement si la
        //    mutuelle a fixé un tarif pour cet acte.
        if (\Illuminate\Support\Facades\Schema::hasColumn('act_types', 'tariff_minor')) {
            $tariff = (int) \App\Models\Tenant\ActType::whereKey($prestation->act_type_id)->value('tariff_minor');
            if ($tariff > 0 && (int) $prestation->total_minor > $tariff) {
                $fired[] = $this->raise($prestation, 'financial', 'watch', 'above_tariff');
            }
        }

        return $fired;
    }

    /** Signal posé par un autre contrôle (pharmacie, urgence sans accord : 2026-11-19). Jamais un refus. */
    public function signal(Prestation $prestation, string $category, string $level, string $code): array
    {
        return $this->raise($prestation, $category, $level, $code);
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
