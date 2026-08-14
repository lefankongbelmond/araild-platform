<?php

use App\Models\Tenant\Account;
use App\Models\Tenant\AccountingEntry;
use App\Models\Tenant\TreasuryMovement;
use App\Services\AccountingService;

/** Module 10 — treasury movements post balanced SYSCOHADA entries. */

beforeEach(function () {
    // Seed the chart from config so account lookups resolve.
    foreach (config('mxconnect.accounting.chart') as $number => [$label, $class]) {
        Account::updateOrCreate(['number' => (string) $number], ['label' => $label, 'class' => $class]);
    }
    $this->svc = app(AccountingService::class);
});

it('posts an inflow contribution as debit cash / credit contributions', function () {
    $m = TreasuryMovement::create([
        'direction' => 'inflow', 'source' => 'contribution', 'amount_minor' => 5000,
        'mode' => 'cash', 'moved_on' => now()->toDateString(),
    ]);

    $entry = $this->svc->postFromMovement($m);

    expect($entry->debitAccount->number)->toBe('571')     // Caisse
        ->and($entry->creditAccount->number)->toBe('7561') // Cotisations
        ->and($entry->amount_minor)->toBe(5000)
        ->and($m->fresh()->accounting_day_id)->not->toBeNull();
})->group('tenant');

it('posts an outflow reimbursement as debit expense / credit bank', function () {
    $m = TreasuryMovement::create([
        'direction' => 'outflow', 'source' => 'reimbursement', 'amount_minor' => 3000,
        'mode' => 'bank', 'moved_on' => now()->toDateString(),
    ]);

    $entry = $this->svc->postFromMovement($m);

    expect($entry->debitAccount->number)->toBe('6011')    // Prestations remboursées
        ->and($entry->creditAccount->number)->toBe('521'); // Banque
})->group('tenant');

it('is idempotent — a posted movement is not posted twice', function () {
    $m = TreasuryMovement::create([
        'direction' => 'inflow', 'source' => 'contribution', 'amount_minor' => 5000,
        'mode' => 'mobile_money', 'moved_on' => now()->toDateString(),
    ]);

    $first  = $this->svc->postFromMovement($m);
    $second = $this->svc->postFromMovement($m->fresh());

    expect($first)->not->toBeNull()
        ->and($second)->toBeNull()
        ->and(AccountingEntry::count())->toBe(1);
})->group('tenant');
