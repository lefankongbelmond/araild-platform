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

    /** Horizon glissant (2026-11-06) : échéances générées jusqu'à N mois après aujourd'hui. */
    public const HORIZON_MONTHS = 12;

    /**
     * Prolonge l'échéancier d'une souscription validée jusqu'à l'horizon glissant, sans jamais
     * dupliquer une période. Avant ce lot, l'échéancier s'arrêtait 12 mois après la date
     * d'effet : passé ce délai, l'adhérent n'avait plus aucune échéance due.
     *
     * @return int échéances créées
     */
    public function extend(Subscription $subscription, ?Carbon $today = null): int
    {
        $status = $subscription->status instanceof \BackedEnum ? $subscription->status->value : $subscription->status;
        if ($status !== 'validated' || ! $subscription->guaranteeVersion) {
            return 0;
        }
        $periodicity = $subscription->guaranteeVersion->periodicity;
        $step = ['monthly' => 1, 'quarterly' => 3, 'annual' => 12][$periodicity] ?? null;
        if (! $step) {
            return 0;
        }
        $horizon = ($today ?? now())->copy()->startOfDay()->addMonthsNoOverflow(self::HORIZON_MONTHS);
        $existing = $subscription->schedules()->pluck('period')->all();
        $start = Carbon::parse($subscription->effective_date);
        $created = 0;

        for ($i = 0; ; $i++) {
            $due = $start->copy()->addMonthsNoOverflow($i * $step);
            if ($due->gt($horizon) || $i > 600) {
                break;
            }
            $period = $this->periodLabel($due, $periodicity);
            if (in_array($period, $existing, true)) {
                continue;
            }
            $subscription->schedules()->create([
                'period' => $period, 'year' => (int) $due->year, 'due_minor' => $subscription->contribution_minor,
                'due_date' => $due->toDateString(), 'status' => $due->lt(now()->startOfDay()) ? 'overdue' : 'to_pay',
            ]);
            $existing[] = $period;
            $created++;
        }

        return $created;
    }

    /**
     * Actualise les échéanciers de la mutuelle courante : prolongation pour les adhérents
     * actifs, puis échéances dépassées marquées « en retard ».
     *
     * @return array{created:int, overdue:int}
     */
    public function refreshAll(): array
    {
        $created = 0;
        $overdueBefore = \App\Models\Tenant\ContributionSchedule::where('status', 'overdue')->count();
        Subscription::with('guaranteeVersion')->where('status', 'validated')
            ->whereHas('member', fn ($q) => $q->where('status', 'active'))
            ->chunkById(200, function ($subs) use (&$created) {
                foreach ($subs as $s) {
                    $created += $this->extend($s);
                }
            });
        \App\Models\Tenant\ContributionSchedule::where('status', 'to_pay')
            ->whereDate('due_date', '<', now()->toDateString())->update(['status' => 'overdue']);
        // Nouvelles échéances en retard : passées « en retard » ET créées directement en retard (rattrapage).
        $overdue = \App\Models\Tenant\ContributionSchedule::where('status', 'overdue')->count() - $overdueBefore;

        return ['created' => $created, 'overdue' => max(0, $overdue)];
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
