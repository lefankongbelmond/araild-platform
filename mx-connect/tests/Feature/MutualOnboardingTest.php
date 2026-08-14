<?php

use App\Models\Central\Country;
use App\Models\Central\Currency;
use App\Models\Central\Locale;
use App\Models\Central\Mutual;
use App\Models\Central\NetworkUser;
use Spatie\Permission\Models\Role;

/** Module 2 — creating a mutual provisions a tenant; authorization is enforced. */

beforeEach(function () {
    foreach (['super_admin','security_admin'] as $r) Role::findOrCreate($r, 'network');
    $this->seed(\Database\Seeders\ConfigReferenceSeeder::class);
});

function superAdmin(): NetworkUser {
    $u = NetworkUser::factory()->create(['mfa_enabled' => true]);
    $u->assignRole('super_admin');
    return $u;
}

it('creates a mutual and provisions its tenant database', function () {
    $admin = superAdmin();
    $country = Country::first();

    $this->actingAs($admin, 'network')->withSession(['mfa_passed' => true])
        ->post(route('network.mutuals.store'), [
            'name' => 'Mutuelle Pilote 1', 'slug' => 'pilote1',
            'country_id' => $country->id,
            'currency_id' => $country->default_currency_id,
            'locale_id' => $country->default_locale_id,
        ])->assertRedirect(route('network.mutuals.index'));

    $mutual = Mutual::where('slug', 'pilote1')->first();
    expect($mutual)->not->toBeNull();
    expect($mutual->domains()->count())->toBe(1);       // subdomain attached
    // stancl created the tenant DB + ran tenant migrations (assert the members table exists):
    tenancy()->initialize($mutual);
    expect(\Illuminate\Support\Facades\Schema::hasTable('members'))->toBeTrue();
    tenancy()->end();
});

it('forbids a non-super-admin from creating a mutual', function () {
    $user = NetworkUser::factory()->create(['mfa_enabled' => true]);
    $user->assignRole('security_admin');
    $country = Country::first();

    $this->actingAs($user, 'network')->withSession(['mfa_passed' => true])
        ->post(route('network.mutuals.store'), [
            'name' => 'X', 'slug' => 'x1',
            'country_id' => $country->id,
            'currency_id' => $country->default_currency_id,
            'locale_id' => $country->default_locale_id,
        ])->assertForbidden();
});

it('approves a pending mutual', function () {
    $admin = superAdmin();
    $mutual = Mutual::factory()->create(['status' => 'pending']);

    $this->actingAs($admin, 'network')->withSession(['mfa_passed' => true])
        ->post(route('network.mutuals.approve', $mutual))
        ->assertRedirect();

    expect($mutual->fresh()->status)->toBe('approved');
});
