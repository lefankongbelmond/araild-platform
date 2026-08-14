<?php

use App\Models\Central\BillingInvoice;
use App\Models\Central\BillingPlan;
use App\Models\Central\Mutual;
use App\Services\BillingService;
use Illuminate\Support\Carbon;

/** Module 20 — SaaS billing engine. */

beforeEach(function () {
    $this->svc = app(BillingService::class);
    $this->mutual = Mutual::factory()->create(['status' => 'approved']);
    $this->plan = BillingPlan::create([
        'code' => 'test', 'name' => 'Test', 'price_minor' => 15000,
        'currency_id' => null, 'interval' => 'monthly', 'active' => true,
    ]);
});

it('invoices the plan price with a unique INV number', function () {
    $sub = $this->svc->subscribe($this->mutual, $this->plan, '2026-01-01');
    $inv = $this->svc->generateInvoice($sub);

    expect($inv->amount_minor)->toBe(15000)
        ->and($inv->status)->toBe('issued')
        ->and($inv->number)->toStartWith('INV-')
        ->and($inv->period_start->toDateString())->toBe('2026-01-01')
        ->and($inv->period_end->toDateString())->toBe('2026-01-31');
})->group('central');

it('does not double-invoice the same period', function () {
    $sub = $this->svc->subscribe($this->mutual, $this->plan, '2026-01-01');
    $this->svc->generateInvoice($sub);
    $again = $this->svc->generateInvoice($sub);

    expect($again)->toBeNull()
        ->and(BillingInvoice::where('billing_subscription_id', $sub->id)->count())->toBe(1);
})->group('central');

it('advances the period and flags overdue on a cycle run', function () {
    $sub = $this->svc->subscribe($this->mutual, $this->plan, '2026-01-01');
    // Period ends 2026-01-31; run the cycle as of 2026-02-01.
    $res = $this->svc->runCycle('2026-02-01');

    expect($res['invoiced'])->toBe(1);
    $sub->refresh();
    expect($sub->current_period_start->toDateString())->toBe('2026-02-01')
        ->and($sub->current_period_end->toDateString())->toBe('2026-02-28');

    // The invoice due_on is issued+15d = today+15d (real now), so force overdue and re-sweep.
    BillingInvoice::where('billing_subscription_id', $sub->id)->update(['due_on' => '2026-01-10']);
    $res2 = $this->svc->runCycle('2026-02-01');
    expect($res2['overdue'])->toBeGreaterThanOrEqual(1);
    $sub->refresh();
    expect($sub->status)->toBe('past_due');
})->group('central');

it('clears past_due when the overdue invoice is paid', function () {
    $sub = $this->svc->subscribe($this->mutual, $this->plan, '2026-01-01');
    $inv = $this->svc->generateInvoice($sub);
    $inv->update(['status' => 'overdue', 'due_on' => '2026-01-10']);
    $sub->update(['status' => 'past_due']);

    $this->svc->markPaid($inv->fresh());

    expect($inv->fresh()->status)->toBe('paid')
        ->and($sub->fresh()->status)->toBe('active');
})->group('central');
