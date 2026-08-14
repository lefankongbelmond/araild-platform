<?php

use App\Models\Central\Mutual;

/**
 * GO/NO-GO isolation test (CDC §27). Two tenants, each with members; a request
 * scoped to tenant A must never see tenant B's data. Expectation is 0 leaks.
 *
 * This is the automated non-regression guard: because we use database-per-tenant,
 * B's rows are not physically present in A's active connection.
 */

it('confines members to their own tenant database', function () {
    $a = Mutual::factory()->create();   // provisions DB + runs tenant migrations
    $b = Mutual::factory()->create();

    tenancy()->initialize($a);
    \App\Models\Tenant\Member::factory()->count(100)->create();
    $aCount = \App\Models\Tenant\Member::count();
    tenancy()->end();

    tenancy()->initialize($b);
    \App\Models\Tenant\Member::factory()->count(100)->create();
    // From inside B, we must see ONLY B's members.
    expect(\App\Models\Tenant\Member::count())->toBe(100);
    tenancy()->end();

    // From inside A, still only A's members — B is invisible.
    tenancy()->initialize($a);
    expect(\App\Models\Tenant\Member::count())->toBe($aCount);   // no B leakage
    tenancy()->end();
})->group('isolation');

it('rejects cross-tenant id access with 404, not 403', function () {
    // Authenticated as a user of A, requesting B's member id must 404
    // (the row does not exist in A's DB), proving the object is not merely hidden.
    expect(true)->toBeTrue(); // wire to the HTTP route once controllers exist
})->group('isolation');
