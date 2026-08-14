<?php

use App\Models\Central\Currency;
use App\Models\Central\NetworkUser;
use Spatie\Permission\Models\Role;

/** Module 3 — configuration is data-driven and super-admin gated. */

beforeEach(function () {
    foreach (['super_admin','security_admin'] as $r) Role::findOrCreate($r, 'network');
    $this->seed(\Database\Seeders\ConfigReferenceSeeder::class);
});

function netUser(string $role): NetworkUser {
    $u = NetworkUser::factory()->create(['mfa_enabled' => true]);
    $u->assignRole($role);
    return $u;
}

it('lets a super admin add a currency', function () {
    $this->actingAs(netUser('super_admin'), 'network')->withSession(['mfa_passed' => true])
        ->post(route('network.config.currencies.store'), [
            'code' => 'NGN', 'name' => 'Naira', 'symbol' => '₦', 'minor_unit' => 2,
        ])->assertRedirect();

    expect(Currency::where('code', 'NGN')->exists())->toBeTrue();
});

it('forbids security admin from adding a currency', function () {
    $this->actingAs(netUser('security_admin'), 'network')->withSession(['mfa_passed' => true])
        ->post(route('network.config.currencies.store'), [
            'code' => 'GHS', 'name' => 'Cedi', 'symbol' => '₵', 'minor_unit' => 2,
        ])->assertForbidden();

    expect(Currency::where('code', 'GHS')->exists())->toBeFalse();
});

it('adds a country with payment providers attached', function () {
    $cur = Currency::where('code', 'XAF')->first();
    $loc = \App\Models\Central\Locale::where('code', 'fr')->first();
    $mtn = \App\Models\Central\PaymentProvider::where('code', 'mtn_momo')->first();

    $this->actingAs(netUser('super_admin'), 'network')->withSession(['mfa_passed' => true])
        ->post(route('network.config.countries.store'), [
            'iso2' => 'SN', 'name' => 'Sénégal',
            'default_currency_id' => $cur->id, 'default_locale_id' => $loc->id,
            'providers' => [$mtn->id],
        ])->assertRedirect();

    $sn = \App\Models\Central\Country::where('iso2', 'SN')->first();
    expect($sn)->not->toBeNull();
    expect($sn->paymentProviders()->count())->toBe(1);
});
