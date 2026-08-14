<?php

namespace App\Services;

use App\Models\Tenant\Account;
use App\Models\Tenant\AccountingDay;
use App\Models\Tenant\AccountingEntry;
use App\Models\Tenant\AccountingPeriod;
use App\Models\Tenant\FiscalYear;
use App\Models\Tenant\TreasuryMovement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * SYSCOHADA accounting engine (CDC §19).
 *
 * Posts double-entry lines and bridges treasury movements into the journal using
 * the mapping in config('mxconnect.accounting'). Every entry carries a single
 * amount against one debit and one credit account, so it is balanced by design.
 * Entries cannot be posted onto a closed accounting day.
 */
class AccountingService
{
    /** Resolve (creating if needed) the accounting day for a date. */
    public function resolveDay(string $date): AccountingDay
    {
        $carbon = Carbon::parse($date);
        $year = (int) $carbon->year;

        $fy = FiscalYear::firstOrCreate(
            ['label' => (string) $year],
            ['start_date' => "{$year}-01-01", 'end_date' => "{$year}-12-31", 'status' => 'open'],
        );

        $period = AccountingPeriod::firstOrCreate(
            ['fiscal_year_id' => $fy->id, 'label' => $carbon->format('Y-m')],
            ['status' => 'open'],
        );

        return AccountingDay::firstOrCreate(
            ['accounting_period_id' => $period->id, 'day' => $carbon->toDateString()],
            ['status' => 'open'],
        );
    }

    /** Post a balanced entry. Throws if the target day is closed. */
    public function post(string $date, string $debitNumber, string $creditNumber, int $amountMinor, string $label, ?string $piece = null): AccountingEntry
    {
        $day = $this->resolveDay($date);
        if ($day->isClosed()) {
            throw new \RuntimeException("Accounting day {$day->day->toDateString()} is closed.");
        }

        $debit  = Account::byNumber($debitNumber)  ?? throw new \RuntimeException("Unknown account {$debitNumber}");
        $credit = Account::byNumber($creditNumber) ?? throw new \RuntimeException("Unknown account {$creditNumber}");

        return AccountingEntry::create([
            'accounting_day_id' => $day->id,
            'piece'             => $piece ?: 'PIECE-' . strtoupper(Str::random(6)),
            'debit_account_id'  => $debit->id,
            'credit_account_id' => $credit->id,
            'amount_minor'      => $amountMinor,
            'label'             => $label,
            'status'            => 'validated',
        ]);
    }

    /**
     * Bridge a treasury movement into the journal.
     *  - inflow : debit the mode's treasury account, credit the source income account.
     *  - outflow: debit the source expense account, credit the mode's treasury account.
     * Idempotent: a movement already linked to a day is skipped (returns null).
     */
    public function postFromMovement(TreasuryMovement $movement): ?AccountingEntry
    {
        if ($movement->accounting_day_id) {
            return null; // already posted
        }

        $cfg = config('mxconnect.accounting');
        $treasury = $cfg['mode_account'][$movement->mode] ?? $cfg['mode_account']['cash'];

        if ($movement->direction === 'inflow') {
            $income = $cfg['source_income'][$movement->source] ?? $cfg['source_income']['other'];
            $entry = $this->post(
                $movement->moved_on->toDateString(),
                debitNumber: $treasury, creditNumber: $income,
                amountMinor: $movement->amount_minor,
                label: "Encaissement {$movement->source}",
                piece: $movement->linked_reference,
            );
        } else {
            $expense = $cfg['source_expense'][$movement->source] ?? $cfg['source_expense']['other'];
            $entry = $this->post(
                $movement->moved_on->toDateString(),
                debitNumber: $expense, creditNumber: $treasury,
                amountMinor: $movement->amount_minor,
                label: "Décaissement {$movement->source}",
                piece: $movement->linked_reference,
            );
        }

        $movement->update(['accounting_day_id' => $entry->accounting_day_id]);

        return $entry;
    }
}
