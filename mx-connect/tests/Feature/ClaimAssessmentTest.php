<?php

use App\Models\Tenant\Ceiling;
use App\Models\Tenant\Guarantee;
use App\Models\Tenant\GuaranteeVersion;
use App\Services\ClaimAssessmentService;

/** Module 9 — the mutual/beneficiary split and ceiling caps. */

function versionWith(float $coverage, float $copay = 0): GuaranteeVersion {
    $g = Guarantee::create(['code' => 'G'.uniqid(), 'name' => 'G', 'status' => 'active']);
    return $g->versions()->create([
        'version_no' => 1, 'valid_from' => now()->subYear()->toDateString(), 'valid_to' => null,
        'base_contribution_minor' => 1000, 'periodicity' => 'monthly',
        'coverage_rate' => $coverage, 'copay_rate' => $copay, 'observation_days' => 0,
    ]);
}

it('splits the total by the coverage rate', function () {
    $v = versionWith(80);
    $r = app(ClaimAssessmentService::class)->assess($v, 1, 10000, 1, now()->toDateString());

    expect($r['mutual_minor'])->toBe(8000)
        ->and($r['beneficiary_minor'])->toBe(2000)
        ->and($r['capped'])->toBeFalse();
})->group('tenant');

it('caps the mutual share at a per-act ceiling', function () {
    $v = versionWith(100);
    Ceiling::create(['guarantee_version_id' => $v->id, 'act_type_id' => 5, 'ceiling_minor' => 6000, 'period' => 'per_act']);

    $r = app(ClaimAssessmentService::class)->assess($v, 5, 10000, 1, now()->toDateString());

    expect($r['mutual_minor'])->toBe(6000)          // capped
        ->and($r['beneficiary_minor'])->toBe(4000)  // remainder falls to member
        ->and($r['capped'])->toBeTrue()
        ->and($r['reason'])->toBe('per_act');
})->group('tenant');

it('reduces an annual ceiling by what was already granted this year', function () {
    $v = versionWith(100);
    Ceiling::create(['guarantee_version_id' => $v->id, 'act_type_id' => null, 'ceiling_minor' => 10000, 'period' => 'annual']);

    // Simulate a prior validated prestation this year that already used 7000.
    $g2 = \App\Models\Tenant\CareClaim::create(['member_id' => 1, 'opened_on' => now()->toDateString(), 'status' => 'open']);
    $g2->prestations()->create([
        'act_type_id' => 1, 'provider_id' => 1, 'guarantee_version_id' => $v->id,
        'care_date' => now()->toDateString(), 'total_minor' => 7000,
        'mutual_part_minor' => 7000, 'beneficiary_part_minor' => 0, 'status' => 'validated',
    ]);

    $r = app(ClaimAssessmentService::class)->assess($v, 1, 5000, 1, now()->toDateString());

    // Only 3000 headroom left under the 10000 annual ceiling.
    expect($r['mutual_minor'])->toBe(3000)
        ->and($r['capped'])->toBeTrue()
        ->and($r['reason'])->toBe('annual');
})->group('tenant');
