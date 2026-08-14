<?php

use App\Models\Central\Mutual;
use App\Models\Central\PaymentTransaction;
use App\Services\PaymentLedgerService;

/**
 * Module 8 — the ledger webhook handler is idempotent: a confirmation is applied
 * once; a replayed webhook is a no-op (no double-count).
 */

beforeEach(function () {
    $this->ledger = app(PaymentLedgerService::class);
});

it('confirms a pending transaction exactly once (idempotent)', function () {
    $tx = PaymentTransaction::create([
        'mutual_id' => 1, 'reference' => 'REF-IDEMP-1', 'member_id' => 1, 'schedule_id' => 1,
        'amount_minor' => 5000, 'currency_id' => 1, 'payment_provider_id' => 1,
        'status' => 'pending', 'reconciliation_status' => 'unreconciled',
    ]);

    $first  = $this->ledger->handleWebhook('REF-IDEMP-1', true, 'PROV-1', ['status' => 'success']);
    $second = $this->ledger->handleWebhook('REF-IDEMP-1', true, 'PROV-1', ['status' => 'success']);

    expect($first)->toBeTrue()        // applied
        ->and($second)->toBeFalse()   // replay ignored
        ->and($tx->fresh()->status->value)->toBe('confirmed');
})->group('central');

it('records a failed transaction without confirming it', function () {
    $tx = PaymentTransaction::create([
        'mutual_id' => 1, 'reference' => 'REF-FAIL-1', 'member_id' => 1, 'schedule_id' => 1,
        'amount_minor' => 5000, 'currency_id' => 1, 'payment_provider_id' => 1,
        'status' => 'pending', 'reconciliation_status' => 'unreconciled',
    ]);

    $this->ledger->handleWebhook('REF-FAIL-1', false, 'PROV-2', ['status' => 'failed']);

    expect($tx->fresh()->status->value)->toBe('failed');
})->group('central');
