<?php

use App\Services\ConsolidationService;

/** Module 18 — network consolidation aggregation (pure logic). */

beforeEach(fn () => $this->svc = app(ConsolidationService::class));

it('sums head-counts globally and money per currency', function () {
    $rows = [
        ['id' => 'a', 'name' => 'A', 'currency' => 'XAF', 'reachable' => true,  'members' => 100, 'active_subscriptions' => 80, 'premiums' => 500000, 'claims' => 300000, 'reserves' => 200000, 'loss_ratio' => 0.6],
        ['id' => 'b', 'name' => 'B', 'currency' => 'XAF', 'reachable' => true,  'members' => 50,  'active_subscriptions' => 40, 'premiums' => 300000, 'claims' => 150000, 'reserves' => 100000, 'loss_ratio' => 0.5],
        ['id' => 'c', 'name' => 'C', 'currency' => 'XOF', 'reachable' => true,  'members' => 20,  'active_subscriptions' => 10, 'premiums' => 100000, 'claims' => 90000,  'reserves' => 50000,  'loss_ratio' => 0.9],
        ['id' => 'd', 'name' => 'D', 'currency' => 'XAF', 'reachable' => false, 'members' => 0,   'active_subscriptions' => 0,  'premiums' => 0,      'claims' => 0,      'reserves' => 0,      'loss_ratio' => null],
    ];

    $t = $this->svc->aggregate($rows);

    expect($t['mutuals'])->toBe(4)
        ->and($t['reachable'])->toBe(3)
        ->and($t['members'])->toBe(170)
        ->and($t['active_subscriptions'])->toBe(130)
        // XAF pooled across A + B (+ unreachable D contributes zero).
        ->and($t['by_currency']['XAF']['premiums'])->toBe(800000)
        ->and($t['by_currency']['XAF']['claims'])->toBe(450000)
        ->and($t['by_currency']['XAF']['reserves'])->toBe(300000)
        // Network loss ratio recomputed on the pooled figures: 450000/800000 = 0.5625.
        ->and($t['by_currency']['XAF']['loss_ratio'])->toBe(0.5625)
        // XOF is a separate bucket, never mixed with XAF.
        ->and($t['by_currency']['XOF']['premiums'])->toBe(100000)
        ->and($t['by_currency']['XOF']['loss_ratio'])->toBe(0.9);
});

it('returns a null loss ratio for a currency with no premiums', function () {
    $rows = [
        ['id' => 'a', 'name' => 'A', 'currency' => 'XAF', 'reachable' => true, 'members' => 5, 'active_subscriptions' => 0, 'premiums' => 0, 'claims' => 0, 'reserves' => 0, 'loss_ratio' => null],
    ];

    expect($this->svc->aggregate($rows)['by_currency']['XAF']['loss_ratio'])->toBeNull();
});
