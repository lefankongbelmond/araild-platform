<?php

use App\Services\OfferPublicationService;

/** Module 19 — public offer summarisation (pure logic). */

beforeEach(fn () => $this->svc = app(OfferPublicationService::class));

it('normalises contributions to a monthly equivalent by periodicity', function () {
    expect($this->svc->monthlyEquivalent(12000, 'monthly'))->toBe(12000)
        ->and($this->svc->monthlyEquivalent(36000, 'quarterly'))->toBe(12000)
        ->and($this->svc->monthlyEquivalent(144000, 'annual'))->toBe(12000);
});

it('summarises min/max/coverage/highlights across current versions', function () {
    $versions = [
        ['name' => 'Base',    'base_contribution_minor' => 5000,   'periodicity' => 'monthly',   'coverage_rate' => 70.0, 'membership_fee_minor' => 2000],
        ['name' => 'Confort', 'base_contribution_minor' => 30000,  'periodicity' => 'quarterly', 'coverage_rate' => 80.0, 'membership_fee_minor' => 2000],
        ['name' => 'Premium', 'base_contribution_minor' => 240000, 'periodicity' => 'annual',    'coverage_rate' => 90.0, 'membership_fee_minor' => 5000],
    ];

    $s = $this->svc->summarize($versions);

    // Monthly equivalents: 5000, 10000, 20000 -> min 5000, max 20000.
    expect($s['guarantees_count'])->toBe(3)
        ->and($s['monthly_contribution_min_minor'])->toBe(5000)
        ->and($s['monthly_contribution_max_minor'])->toBe(20000)
        ->and($s['coverage_min'])->toBe(70.0)
        ->and($s['coverage_max'])->toBe(90.0)
        ->and($s['membership_fee_min_minor'])->toBe(2000)
        ->and($s['membership_fee_max_minor'])->toBe(5000)
        ->and($s['highlights'])->toBe(['Base', 'Confort', 'Premium']);
});

it('returns an empty summary when a mutual has no guarantees', function () {
    $s = $this->svc->summarize([]);

    expect($s['guarantees_count'])->toBe(0)
        ->and($s['monthly_contribution_min_minor'])->toBeNull()
        ->and($s['highlights'])->toBe([]);
});
