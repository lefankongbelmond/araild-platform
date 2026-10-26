<?php

namespace App\Services;

use App\Models\Tenant\Subscription;
use Illuminate\Support\Carbon;

/**
 * Builds the contribution schedule for a validated subscription (CDC §13).
 * Generates a rolling 12-month horizon: 12 monthly / 4 quarterly / 1 annual line(s),
 * each carrying the subscription's contribution amount and a due date.
 */
class ScheduleGenerationService
{
    /** @return int number of schedule lines created */
    public function generate(Subscription $subscription): int
    {
        // Idempotent: never duplicate a schedule for a period already present.
        $existing = $subscription->schedules()->pluck('period')->all();

        [$count, $stepMonths] = match ($subscription->guaranteeVersion->periodicity) {
            'monthly'   => [12, 1],
            'quarterly' => [4, 3],
            'annual'    => [1, 12],
        };

        $start = Carbon::parse($subscription->effective_date);
        $created = 0;

        for ($i = 0; $i < $count; $i++) {
            $due = (clone $start)->addMonths($i * $stepMonths);
            $period = $this->periodLabel($due, $subscription->guaranteeVersion->periodicity);

            if (in_array($period, $existing, true)) {
                continue;
            }

            $subscription->schedules()->create([
                'period'    => $period,
                'year'      => (int) $due->year,
                'due_minor' => $subscription->contribution_minor,
                'due_date'  => $due->toDateString(),
                'status'    => 'to_pay',
            ]);
            $created++;
        }

        return $created;
    }

    private function periodLabel(Carbon $date, string $periodicity): string
    {
        return match ($periodicity) {
            'monthly'   => $date->format('Y-m'),
            'quarterly' => $date->format('Y') . '-T' . (int) ceil($date->month / 3),
            'annual'    => $date->format('Y'),
        };
    }
}
