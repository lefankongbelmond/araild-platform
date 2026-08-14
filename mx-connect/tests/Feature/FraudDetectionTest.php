<?php

use App\Models\Tenant\Alert;
use App\Models\Tenant\CareClaim;
use App\Models\Tenant\Prestation;
use App\Services\FraudDetectionService;

/** Module 9 — anti-fraud rules raise alerts (never block). */

function prestationFor(int $memberId, int $actTypeId, string $careDate, string $status = 'captured'): Prestation {
    $claim = CareClaim::create(['member_id' => $memberId, 'opened_on' => now()->toDateString(), 'status' => 'open']);
    return $claim->prestations()->create([
        'act_type_id' => $actTypeId, 'provider_id' => 1, 'guarantee_version_id' => 1,
        'care_date' => $careDate, 'total_minor' => 1000,
        'mutual_part_minor' => 800, 'beneficiary_part_minor' => 200, 'status' => $status,
    ]);
}

it('flags a duplicate care event as critical', function () {
    $date = now()->toDateString();
    prestationFor(1, 7, $date, 'validated');       // pre-existing
    $new = prestationFor(1, 7, $date);             // same member+act+date

    $fired = app(FraudDetectionService::class)->screen($new, 1, false);

    expect(collect($fired)->pluck('category'))->toContain('duplicate')
        ->and(Alert::where('category', 'duplicate')->where('level', 'critical')->exists())->toBeTrue();
})->group('tenant');

it('flags a future care date', function () {
    $new = prestationFor(1, 3, now()->addWeek()->toDateString());
    $fired = app(FraudDetectionService::class)->screen($new, 1, false);
    expect(collect($fired)->pluck('category'))->toContain('temporal');
})->group('tenant');

it('raises a financial alert when the ceiling was capped', function () {
    $new = prestationFor(1, 9, now()->toDateString());
    $fired = app(FraudDetectionService::class)->screen($new, 1, true);   // capped = true
    expect(collect($fired)->pluck('category'))->toContain('financial');
})->group('tenant');

it('does not flag a clean, first-time prestation', function () {
    $new = prestationFor(1, 2, now()->toDateString());
    $fired = app(FraudDetectionService::class)->screen($new, 1, false);
    expect($fired)->toBeEmpty();
})->group('tenant');
