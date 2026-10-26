<?php

namespace App\Services;

use App\Models\Tenant\AccountingDay;
use App\Models\Tenant\TreasuryMovement;

/**
 * Daily cash close — "arrêté de caisse" (CDC §18).
 *
 * Computes the THEORETICAL cash balance (opening + cash inflows − cash outflows
 * for the day) and records the ACTUAL counted balance. The difference is the
 * discrepancy the treasurer must justify. Closing freezes the day: no further
 * entries or cash movements may be dated to it.
 */
class DailyCloseService
{
    /** Theoretical closing cash balance for a day (minor units). */
    public function theoreticalCash(string $date): int
    {
        $opening = $this->openingCash($date);

        $in = (int) TreasuryMovement::where('direction', 'inflow')->where('mode', 'cash')
            ->whereDate('moved_on', $date)->sum('amount_minor');
        $out = (int) TreasuryMovement::where('direction', 'outflow')->where('mode', 'cash')
            ->whereDate('moved_on', $date)->sum('amount_minor');

        return $opening + $in - $out;
    }

    /**
     * Close the day.
     * @return int discrepancy (actual − theoretical); 0 means the cash reconciles.
     */
    public function close(AccountingDay $day, int $actualMinor): int
    {
        if ($day->isClosed()) {
            throw new \RuntimeException('Day already closed.');
        }

        $theoretical = $this->theoreticalCash($day->day->toDateString());

        $day->update([
            'theoretical_balance_minor' => $theoretical,
            'actual_balance_minor'      => $actualMinor,
            'status'                    => 'closed',
        ]);

        return $actualMinor - $theoretical;
    }

    /** Opening cash = the most recent previously-closed day's actual balance. */
    private function openingCash(string $date): int
    {
        $previous = AccountingDay::where('status', 'closed')
            ->whereDate('day', '<', $date)
            ->orderByDesc('day')->first();

        return (int) ($previous->actual_balance_minor ?? 0);
    }
}
