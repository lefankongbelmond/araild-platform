<?php

use App\Models\Tenant\TreasuryMovement;
use App\Services\AccountingService;
use App\Services\DailyCloseService;

/** Module 10 — cash close computes theoretical balance and the discrepancy. */

beforeEach(function () {
    $this->accounting = app(AccountingService::class);
    $this->close = app(DailyCloseService::class);
});

it('computes theoretical cash as inflows minus outflows (cash only)', function () {
    $today = now()->toDateString();
    TreasuryMovement::create(['direction' => 'inflow', 'source' => 'contribution', 'amount_minor' => 10000, 'mode' => 'cash', 'moved_on' => $today]);
    TreasuryMovement::create(['direction' => 'outflow', 'source' => 'reimbursement', 'amount_minor' => 3000, 'mode' => 'cash', 'moved_on' => $today]);
    // A mobile-money inflow must NOT count toward physical cash.
    TreasuryMovement::create(['direction' => 'inflow', 'source' => 'contribution', 'amount_minor' => 9999, 'mode' => 'mobile_money', 'moved_on' => $today]);

    expect($this->close->theoreticalCash($today))->toBe(7000);
})->group('tenant');

it('records the discrepancy between counted and theoretical', function () {
    $today = now()->toDateString();
    TreasuryMovement::create(['direction' => 'inflow', 'source' => 'contribution', 'amount_minor' => 8000, 'mode' => 'cash', 'moved_on' => $today]);

    $day = $this->accounting->resolveDay($today);
    $discrepancy = $this->close->close($day, 7500);   // counted 7500, theoretical 8000

    expect($discrepancy)->toBe(-500)
        ->and($day->fresh()->status)->toBe('closed')
        ->and($day->fresh()->theoretical_balance_minor)->toBe(8000);
})->group('tenant');

it('refuses to post an entry onto a closed day', function () {
    $today = now()->toDateString();
    $day = $this->accounting->resolveDay($today);
    $this->close->close($day, 0);

    expect(fn () => $this->accounting->post($today, '571', '7561', 1000, 'x'))
        ->toThrow(RuntimeException::class);
})->group('tenant');
