<?php

use App\Models\Tenant\Guarantee;
use App\Models\Tenant\Member;
use App\Models\Tenant\Subscription;
use App\Models\Tenant\User;
use App\Services\ScheduleGenerationService;

/**
 * Module 6 — separation of duties: the agent who captures a subscription may not
 * validate it; a different controller must. Validation generates the schedule.
 */

function tenantUser(string $role): User {
    $u = User::factory()->create();
    $u->assignRole($role);
    return $u;
}

function makeCapturedSubscription(int $capturedBy, string $periodicity = 'monthly'): Subscription {
    $member = Member::factory()->create(['status' => 'active']);
    $g = Guarantee::create(['code' => 'STD', 'name' => 'Std', 'status' => 'active']);
    $v = $g->versions()->create([
        'version_no' => 1, 'valid_from' => now()->subMonth()->toDateString(), 'valid_to' => null,
        'base_contribution_minor' => 5000, 'periodicity' => $periodicity,
        'coverage_rate' => 80, 'copay_rate' => 20, 'observation_days' => 30,
    ]);

    return Subscription::create([
        'member_id' => $member->id, 'guarantee_version_id' => $v->id,
        'contribution_minor' => 5000, 'effective_date' => now()->toDateString(),
        'observation_ends_on' => now()->addDays(30)->toDateString(),
        'status' => 'captured', 'captured_by' => $capturedBy,
    ]);
}

it('blocks the capturing agent from validating their own subscription', function () {
    $agent = tenantUser('enrollment_agent');
    $sub = makeCapturedSubscription($agent->id);

    // Agent also happens to hold the validate permission in this edge case:
    $agent->givePermissionTo('subscription.validate');

    $this->actingAs($agent)->withSession(['mfa_passed' => true])
        ->post(route('subscriptions.validate', $sub))
        ->assertSessionHas('error');

    expect($sub->fresh()->status->value)->toBe('captured');
})->group('tenant');

it('lets a different controller validate and generates the schedule', function () {
    $agent = tenantUser('enrollment_agent');
    $checker = tenantUser('controller_validator');
    $sub = makeCapturedSubscription($agent->id, 'monthly');

    $this->actingAs($checker)->withSession(['mfa_passed' => true])
        ->post(route('subscriptions.validate', $sub))
        ->assertRedirect();

    $sub->refresh();
    expect($sub->status->value)->toBe('validated')
        ->and($sub->validated_by)->toBe($checker->id)
        ->and($sub->schedules()->count())->toBe(12);   // 12 monthly lines
})->group('tenant');

it('generates 4 lines for a quarterly guarantee', function () {
    $sub = makeCapturedSubscription(tenantUser('enrollment_agent')->id, 'quarterly');
    $created = app(ScheduleGenerationService::class)->generate($sub);
    expect($created)->toBe(4);
})->group('tenant');

it('is idempotent — regenerating does not duplicate periods', function () {
    $sub = makeCapturedSubscription(tenantUser('enrollment_agent')->id, 'monthly');
    $svc = app(ScheduleGenerationService::class);
    $svc->generate($sub);
    $svc->generate($sub);   // second run
    expect($sub->schedules()->count())->toBe(12);
})->group('tenant');
