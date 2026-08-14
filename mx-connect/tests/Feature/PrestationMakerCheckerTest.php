<?php

use App\Models\Tenant\CareClaim;
use App\Models\Tenant\Prestation;
use App\Models\Tenant\User;

/** Module 9 — prestation lifecycle: only a controller validates a captured prestation. */

it('lets a controller validate a captured prestation', function () {
    $checker = User::factory()->create();
    $checker->assignRole('controller_validator');

    $claim = CareClaim::create(['member_id' => 1, 'opened_on' => now()->toDateString(), 'status' => 'open']);
    $p = $claim->prestations()->create([
        'act_type_id' => 1, 'provider_id' => 1, 'guarantee_version_id' => 1,
        'care_date' => now()->toDateString(), 'total_minor' => 5000,
        'mutual_part_minor' => 4000, 'beneficiary_part_minor' => 1000, 'status' => 'captured',
    ]);

    $this->actingAs($checker)->withSession(['mfa_passed' => true])
        ->post(route('claims.prestations.validate', [$claim, $p]))
        ->assertRedirect();

    expect($p->fresh()->status)->toBe('validated');
})->group('tenant');

it('forbids a benefits_manager (maker) from validating', function () {
    $agent = User::factory()->create();
    $agent->assignRole('benefits_manager');

    $claim = CareClaim::create(['member_id' => 1, 'opened_on' => now()->toDateString(), 'status' => 'open']);
    $p = $claim->prestations()->create([
        'act_type_id' => 1, 'provider_id' => 1, 'guarantee_version_id' => 1,
        'care_date' => now()->toDateString(), 'total_minor' => 5000,
        'mutual_part_minor' => 4000, 'beneficiary_part_minor' => 1000, 'status' => 'captured',
    ]);

    $this->actingAs($agent)->withSession(['mfa_passed' => true])
        ->post(route('claims.prestations.validate', [$claim, $p]))
        ->assertForbidden();

    expect($p->fresh()->status)->toBe('captured');
})->group('tenant');
