<?php

use App\Models\Central\NetworkUser;
use Spatie\Permission\Models\Role;

/** Network auth + MFA enforcement for sensitive roles. */

beforeEach(function () {
    Role::findOrCreate('super_admin', 'network');
});

it('logs in a network user with valid credentials', function () {
    $user = NetworkUser::factory()->create(['password' => bcrypt('secret-pass')]);

    $this->post(route('network.login'), [
        'email' => $user->email, 'password' => 'secret-pass',
    ])->assertRedirect();

    $this->assertAuthenticatedAs($user, 'network');
});

it('rejects invalid credentials', function () {
    $user = NetworkUser::factory()->create(['password' => bcrypt('secret-pass')]);

    $this->from(route('network.login'))->post(route('network.login'), [
        'email' => $user->email, 'password' => 'wrong',
    ])->assertRedirect(route('network.login'))->assertSessionHasErrors('email');

    $this->assertGuest('network');
});

it('forces a sensitive-role user without MFA to enrol', function () {
    $user = NetworkUser::factory()->create(['mfa_enabled' => false]);
    $user->assignRole('super_admin');

    $this->actingAs($user, 'network')
        ->get(route('network.mutuals.index'))
        ->assertRedirect(route('mfa.enroll'));
});

it('lets a sensitive-role user through once MFA is passed in session', function () {
    $user = NetworkUser::factory()->create(['mfa_enabled' => true]);
    $user->assignRole('super_admin');

    $this->actingAs($user, 'network')
        ->withSession(['mfa_passed' => true])
        ->get(route('network.mutuals.index'))
        ->assertOk();
});
