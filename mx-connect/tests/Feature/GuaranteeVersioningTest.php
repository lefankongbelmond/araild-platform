<?php

use App\Models\Tenant\Guarantee;
use App\Models\Tenant\GuaranteeVersion;
use App\Services\GuaranteeVersioningService;

/**
 * Module 4 — the load-bearing rule: editing terms creates a NEW version and
 * closes the current one; it never mutates a running version. Runs in a tenant.
 */

it('creates a first version valid from today with no end date', function () {
    $svc = app(GuaranteeVersioningService::class);
    $g = $svc->createWithFirstVersion(
        ['code' => 'STD', 'name' => 'Santé Standard', 'status' => 'active'],
        ['base_contribution_minor' => 5000, 'periodicity' => 'monthly',
         'coverage_rate' => 80, 'copay_rate' => 20, 'membership_fee_minor' => 2000,
         'observation_days' => 30],
    );

    $v1 = $g->versions()->first();
    expect($v1->version_no)->toBe(1)
        ->and($v1->valid_to)->toBeNull();
})->group('tenant');

it('closes the current version the day before the successor takes effect', function () {
    $svc = app(GuaranteeVersioningService::class);
    $g = $svc->createWithFirstVersion(
        ['code' => 'STD2', 'name' => 'Standard', 'status' => 'active'],
        ['base_contribution_minor' => 5000, 'periodicity' => 'monthly',
         'coverage_rate' => 80, 'copay_rate' => 20, 'membership_fee_minor' => 0,
         'observation_days' => 30],
    );

    $effective = now()->addMonth()->toDateString();
    $v2 = $svc->newVersion($g, [
        'base_contribution_minor' => 6000, 'periodicity' => 'monthly',
        'coverage_rate' => 85, 'copay_rate' => 15, 'membership_fee_minor' => 0,
        'observation_days' => 30,
    ], effectiveFrom: $effective);

    $v1 = $g->versions()->where('version_no', 1)->first();

    expect($v2->version_no)->toBe(2)
        ->and($v2->valid_from->toDateString())->toBe($effective)
        ->and($v2->valid_to)->toBeNull()
        // v1 now ends the day before v2 starts — no overlap, no gap
        ->and($v1->valid_to->toDateString())->toBe(\Illuminate\Support\Carbon::parse($effective)->subDay()->toDateString());
})->group('tenant');

it('refuses to edit a locked version', function () {
    $svc = app(GuaranteeVersioningService::class);
    $v = new GuaranteeVersion(['locked' => true]);

    $svc->assertEditable($v);
})->throws(\RuntimeException::class)->group('tenant');

it('selects the version applicable on a given care date', function () {
    $g = Guarantee::create(['code' => 'H', 'name' => 'H', 'status' => 'active']);
    $g->versions()->create(['version_no' => 1, 'valid_from' => '2026-01-01', 'valid_to' => '2026-06-30',
        'base_contribution_minor' => 1, 'periodicity' => 'monthly', 'coverage_rate' => 70, 'copay_rate' => 30, 'observation_days' => 0]);
    $g->versions()->create(['version_no' => 2, 'valid_from' => '2026-07-01', 'valid_to' => null,
        'base_contribution_minor' => 1, 'periodicity' => 'monthly', 'coverage_rate' => 80, 'copay_rate' => 20, 'observation_days' => 0]);

    expect(GuaranteeVersion::applicableOn($g->id, '2026-03-15')->version_no)->toBe(1)
        ->and(GuaranteeVersion::applicableOn($g->id, '2026-09-15')->version_no)->toBe(2);
})->group('tenant');
