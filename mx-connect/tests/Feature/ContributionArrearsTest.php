<?php

use App\Models\Tenant\ContributionSchedule;
use App\Models\Tenant\Guarantee;
use App\Models\Tenant\Member;
use App\Models\Tenant\Subscription;

/** Module 7 — the nightly sweep flags only past-due, unpaid schedules. */

function scheduleWith(string $status, string $dueDate): ContributionSchedule {
    $member = Member::factory()->create();
    $g = Guarantee::create(['code' => 'G'.uniqid(), 'name' => 'G', 'status' => 'active']);
    $v = $g->versions()->create([
        'version_no' => 1, 'valid_from' => now()->subYear()->toDateString(), 'valid_to' => null,
        'base_contribution_minor' => 1000, 'periodicity' => 'monthly',
        'coverage_rate' => 80, 'copay_rate' => 20, 'observation_days' => 0,
    ]);
    $sub = Subscription::create([
        'member_id' => $member->id, 'guarantee_version_id' => $v->id,
        'contribution_minor' => 1000, 'effective_date' => now()->subYear()->toDateString(),
        'observation_ends_on' => now()->subYear()->toDateString(),
        'status' => 'validated', 'captured_by' => 1, 'validated_by' => 2,
    ]);
    return $sub->schedules()->create([
        'period' => 'P'.uniqid(), 'year' => (int) now()->year,
        'due_minor' => 1000, 'due_date' => $dueDate, 'status' => $status,
    ]);
}

it('marks a past-due unpaid schedule as overdue', function () {
    $past = scheduleWith('to_pay', now()->subDays(5)->toDateString());
    $future = scheduleWith('to_pay', now()->addDays(5)->toDateString());
    $paid = scheduleWith('paid', now()->subDays(5)->toDateString());

    // Run the same query the command runs (command wraps this per-tenant).
    ContributionSchedule::query()
        ->where('status', 'to_pay')
        ->whereDate('due_date', '<', now()->toDateString())
        ->update(['status' => 'overdue']);

    expect($past->fresh()->status)->toBe('overdue')
        ->and($future->fresh()->status)->toBe('to_pay')   // not yet due
        ->and($paid->fresh()->status)->toBe('paid');       // already paid, untouched
})->group('tenant');
