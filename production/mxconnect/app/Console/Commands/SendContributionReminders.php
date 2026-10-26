<?php

namespace App\Console\Commands;

use App\Models\Central\Mutual;
use App\Models\Tenant\ContributionSchedule;
use App\Notifications\ContributionReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Sends contribution reminders at the configured offsets (default -3, 0, +7 days
 * around the due date). Runs per tenant; a member with a linked public account is
 * notified via their preferred channel. A one-click payment link is included.
 */
class SendContributionReminders extends Command
{
    protected $signature = 'contributions:remind';
    protected $description = 'Send contribution reminders at configured offsets (per tenant).';

    public function handle(): int
    {
        $offsets = config('mxconnect.contribution_reminder_offsets', [-3, 0, 7]);
        $today = Carbon::today();
        $targetDates = array_map(fn ($o) => $today->copy()->subDays($o)->toDateString(), $offsets);
        $sent = 0;

        Mutual::where('status', 'approved')->each(function (Mutual $mutual) use ($targetDates, &$sent) {
            $mutual->run(function () use ($targetDates, &$sent) {
                ContributionSchedule::with('subscription.member')
                    ->whereIn('status', ['to_pay', 'overdue'])
                    ->whereIn('due_date', $targetDates)
                    ->each(function (ContributionSchedule $schedule) use (&$sent) {
                        $member = $schedule->subscription?->member;
                        if ($member) {
                            $member->notify(new ContributionReminder($schedule));
                            $sent++;
                        }
                    });
            });
        });

        $this->info("Dispatched {$sent} reminder(s).");
        return self::SUCCESS;
    }
}
