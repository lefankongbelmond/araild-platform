<?php

namespace App\Console\Commands;

use App\Models\Central\Mutual;
use App\Models\Tenant\ContributionSchedule;
use Illuminate\Console\Command;

/**
 * Daily arrears sweep (CDC §13). For every approved mutual, marks schedules
 * whose due date has passed and are still unpaid as 'overdue'. Tenant-aware:
 * each mutual is processed inside its own database.
 */
class SweepOverdueContributions extends Command
{
    protected $signature = 'contributions:sweep-overdue';
    protected $description = 'Mark unpaid, past-due contribution schedules as overdue (per tenant).';

    public function handle(): int
    {
        $total = 0;

        Mutual::where('status', 'approved')->each(function (Mutual $mutual) use (&$total) {
            $mutual->run(function () use (&$total) {   // stancl: run closure inside tenant context
                $count = ContributionSchedule::query()
                    ->where('status', 'to_pay')
                    ->whereDate('due_date', '<', now()->toDateString())
                    ->update(['status' => 'overdue']);
                $total += $count;
            });
        });

        $this->info("Marked {$total} schedule(s) overdue.");
        return self::SUCCESS;
    }
}
