<?php

use App\Enums\OrganizationManagerRole;
use App\Enums\OrganizationStatus;
use App\Models\Central\Organization;
use App\Models\Central\PublicAccount;
use Illuminate\Support\Facades\Hash;

require_once __DIR__.'/SocialHelpers.php';

/** Lot 2 — public-account auth and organization profiles. */

it('registers a public account, hashes the password and signs it in', function () {
    $this->post(route('register'), [
        'first_name' => 'Belmond', 'last_name' => 'Lefankong', 'phone' => '+237650708330',
        'password' => 'motdepasse-solide', 'password_confirmation' => 'motdepasse-solide', 'terms' => '1',
    ])->assertRedirect(route('feed'));

    $account = PublicAccount::where('phone', '+237650708330')->first();
    expect($account)->not->toBeNull()
        ->and(Hash::check('motdepasse-solide', $account->password))->toBeTrue();
    $this->assertAuthenticatedAs($account, 'member');
});

it('refuses a duplicate phone number', function () {
    socialAccount();
    $existing = PublicAccount::first();

    $this->post(route('register'), [
        'first_name' => 'X', 'last_name' => 'Y', 'phone' => $existing->phone,
        'password' => 'motdepasse-solide', 'password_confirmation' => 'motdepasse-solide', 'terms' => '1',
    ])->assertSessionHasErrors('phone');
});

it('logs in with phone and password, and rejects a wrong password', function () {
    $account = socialAccount();

    $this->post(route('login'), ['phone' => $account->phone, 'password' => 'wrong'])->assertSessionHasErrors('phone');
    $this->assertGuest('member');

    $this->post(route('login'), ['phone' => $account->phone, 'password' => 'secret-password'])->assertRedirect(route('feed'));
    $this->assertAuthenticatedAs($account, 'member');
});

it('sends guests of protected pages to the right login', function () {
    $this->get(route('my.requests'))->assertRedirect(route('login'));
    $this->get('/reseau/mutuelles')->assertRedirect(route('network.login'));
    $this->get(route('network.login'))->assertOk();
});

it('rejects unknown domains instead of storing them', function () {
    $owner = socialAccount();

    $this->actingAs($owner, 'member')->post(route('organizations.store'), [
        'name' => 'Asso', 'type' => 'association', 'domains' => ['hacked'],
    ])->assertSessionHasErrors('domains.0');
});

it('creates a published, owner-managed organization and ignores privileged fields (never self-verified)', function () {
    $owner = socialAccount();

    $this->actingAs($owner, 'member')->post(route('organizations.store'), [
        'name' => 'Initiatives Vertes Afrique', 'type' => 'ong', 'domains' => ['environnement'],
        'verified_at' => now()->toDateTimeString(), 'status' => 'suspended', 'mutual_id' => 'mut_01',
        'stats' => [['label' => 'Arbres plantés', 'value' => '500'], ['label' => '', 'value' => '']],
    ])->assertRedirect();

    $org = Organization::where('slug', 'initiatives-vertes-afrique')->firstOrFail();
    expect($org->isVerified())->toBeFalse()
        ->and($org->mutual_id)->toBeNull()
        ->and($org->status)->toBe(OrganizationStatus::Published)
        ->and($org->roleOf($owner))->toBe(OrganizationManagerRole::Owner)
        ->and($org->stats)->toBe(['Arbres plantés' => '500']);
});

it('only lets owners and admins edit the profile', function () {
    $owner = socialAccount();
    $editor = socialAccount('Editor');
    $stranger = socialAccount('Stranger');
    $org = socialOrg($owner);
    addManager($org, $editor, OrganizationManagerRole::Editor);

    $payload = ['name' => 'Nouveau nom', 'type' => 'mutual'];
    $this->actingAs($stranger, 'member')->put(route('organizations.update', $org), $payload)->assertForbidden();
    $this->actingAs($editor, 'member')->put(route('organizations.update', $org), $payload)->assertForbidden();
    $this->actingAs($owner, 'member')->put(route('organizations.update', $org), $payload)->assertRedirect();

    expect($org->refresh()->name)->toBe('Nouveau nom');
});

it('counts followers once, and unfollowing decrements', function () {
    $org = socialOrg(socialAccount());
    $fan = socialAccount('Fan');

    $this->actingAs($fan, 'member')->post(route('organizations.follow', $org));
    $this->actingAs($fan, 'member')->post(route('organizations.follow', $org));
    expect($org->refresh()->followers_count)->toBe(1);

    $this->actingAs($fan, 'member')->delete(route('organizations.unfollow', $org));
    expect($org->refresh()->followers_count)->toBe(0);
});

it('hides a suspended organization from the public but not from its managers', function () {
    $owner = socialAccount();
    $org = socialOrg($owner);
    $org->update(['status' => OrganizationStatus::Suspended]);

    $this->get(route('organizations.show', $org))->assertNotFound();
    $this->actingAs($owner, 'member')->get(route('organizations.show', $org))->assertOk();
});
