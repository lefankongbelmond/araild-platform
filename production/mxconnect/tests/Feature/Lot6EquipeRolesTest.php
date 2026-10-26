<?php

/*
 * Lot 6 — « Équipe et rôles » du back-office des mutuelles, testé dans une VRAIE base
 * de mutuelle (mx_tests_tenant, provisionnée comme sur LWS : mode manuel, base vide).
 */

use App\Models\Central\Country;
use App\Models\Central\Mutual;
use App\Models\Tenant\User;
use App\Services\MutualProvisioningService;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(\Database\Seeders\ConfigReferenceSeeder::class);
    $c = config('database.connections.central');
    // Base de mutuelle vide (comme une base créée dans le panneau LWS).
    $pdo = new PDO("mysql:host={$c['host']};port={$c['port']};dbname=mx_tests_tenant", $c['username'], $c['password']);
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
        $pdo->exec("DROP TABLE `{$t}`");
    }
    $country = Country::where('active', true)->firstOrFail();
    $this->mutual = app(MutualProvisioningService::class)->create([
        'name' => 'Mutuelle Équipe', 'slug' => 'equipe' . random_int(100, 999), 'country_id' => $country->id,
        'currency_id' => $country->default_currency_id, 'locale_id' => $country->default_locale_id,
        'db_name' => 'mx_tests_tenant', 'db_username' => $c['username'], 'db_password' => $c['password'],
    ]);
    $this->host = 'http://' . $this->mutual->domains()->value('domain');
    $this->mutual->run(fn () => app(MutualProvisioningService::class)->createUser($this->mutual, 'admin@equipe.test', 'Ada', 'Admin', 'mutual_admin', 'MotDePasse-Admin-2026'));
    // L'administrateur a déjà choisi son mot de passe et activé la double authentification.
    $this->mutual->run(fn () => User::where('email', 'admin@equipe.test')->update(['must_change_password' => false, 'two_factor_confirmed_at' => now()]));
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

/** Utilisateur de la mutuelle de test (chargé dans la base de la mutuelle). */
function tenantUser(string $email): User
{
    return test()->mutual->run(fn () => User::with('roles')->where('email', $email)->firstOrFail());
}

function asAdmin(): \Illuminate\Foundation\Testing\TestCase
{
    return test()->actingAs(tenantUser('admin@equipe.test'), 'web')->withSession(['mfa_passed' => true]);
}

it('ajoute une personne avec un mot de passe provisoire affiché une fois, à changer à la 1re connexion', function () {
    $res = asAdmin()->post($this->host . '/equipe', [
        'first_name' => 'Awa', 'last_name' => 'Agent', 'email' => 'Awa@Equipe.test', 'roles' => ['enrollment_agent'],
    ])->assertRedirect($this->host . '/equipe');
    [$name, $email, $temp] = session('team_password');
    expect($email)->toBe('awa@equipe.test')->and(strlen($temp))->toBe(14);

    $awa = tenantUser('awa@equipe.test');
    expect($awa->getRoleNames()->all())->toBe(['enrollment_agent'])->and($awa->must_change_password)->toBeTrue()
        ->and(Hash::check($temp, $awa->password))->toBeTrue();

    // 1re connexion : tout écran renvoie vers « Mon mot de passe ».
    $this->actingAs($awa, 'web')->withSession(['mfa_passed' => true])->get($this->host . '/adherents')->assertRedirect($this->host . '/mon-mot-de-passe');
    $this->post($this->host . '/mon-mot-de-passe', ['current_password' => $temp, 'password' => 'NouveauSecret2026', 'password_confirmation' => 'NouveauSecret2026'])
        ->assertRedirect();
    expect(tenantUser('awa@equipe.test')->must_change_password)->toBeFalse();
});

it('applique le contrôle « 4 yeux » : rôles incompatibles refusés', function () {
    asAdmin()->post($this->host . '/equipe', ['first_name' => 'Cumul', 'last_name' => 'Interdit', 'email' => 'cumul@equipe.test',
        'roles' => ['enrollment_agent', 'controller_validator']])->assertSessionHasErrors('roles');
    expect($this->mutual->run(fn () => User::where('email', 'cumul@equipe.test')->exists()))->toBeFalse();
});

it('protège le dernier administrateur et interdit de se désactiver soi-même', function () {
    $admin = tenantUser('admin@equipe.test');
    asAdmin()->post($this->host . "/equipe/{$admin->id}/modifier", ['first_name' => 'Ada', 'last_name' => 'Admin', 'roles' => ['community_manager']])
        ->assertSessionHasErrors('roles');
    expect(tenantUser('admin@equipe.test')->hasRole('mutual_admin'))->toBeTrue();
    asAdmin()->post($this->host . "/equipe/{$admin->id}/activation")->assertSessionHasErrors('team');
    expect(tenantUser('admin@equipe.test')->active)->toBeTrue();
});

it('désactive un compte : la personne est déconnectée immédiatement', function () {
    asAdmin()->post($this->host . '/equipe', ['first_name' => 'Cm', 'last_name' => 'Com', 'email' => 'cm@equipe.test', 'roles' => ['community_manager']]);
    $cm = tenantUser('cm@equipe.test');
    asAdmin()->post($this->host . "/equipe/{$cm->id}/activation")->assertRedirect();
    expect(tenantUser('cm@equipe.test')->active)->toBeFalse();

    $this->actingAs(tenantUser('cm@equipe.test'), 'web')->get($this->host . '/reseau-social')->assertRedirect($this->host . '/connexion');
});

it('réinitialise le mot de passe et la double authentification d’un membre de l’équipe', function () {
    asAdmin()->post($this->host . '/equipe', ['first_name' => 'Tre', 'last_name' => 'Sorier', 'email' => 'tres@equipe.test', 'roles' => ['treasurer_accountant']]);
    $t = tenantUser('tres@equipe.test');
    $this->mutual->run(fn () => User::whereKey($t->id)->update(['must_change_password' => false, 'two_factor_confirmed_at' => now(), 'two_factor_secret' => encrypt('ABC')]));

    asAdmin()->post($this->host . "/equipe/{$t->id}/mot-de-passe")->assertRedirect();
    $temp = session('team_password')[2];
    $t = tenantUser('tres@equipe.test');
    expect(Hash::check($temp, $t->password))->toBeTrue()->and($t->must_change_password)->toBeTrue();

    asAdmin()->post($this->host . "/equipe/{$t->id}/double-authentification")->assertRedirect();
    expect(tenantUser('tres@equipe.test')->two_factor_confirmed_at)->toBeNull();
});

it('réserve « Équipe et rôles » à l’administrateur et affiche des droits calculés depuis le code', function () {
    asAdmin()->post($this->host . '/equipe', ['first_name' => 'Cm', 'last_name' => 'Com', 'email' => 'cm2@equipe.test', 'roles' => ['community_manager']]);
    $cm = tenantUser('cm2@equipe.test');
    $this->mutual->run(fn () => User::whereKey($cm->id)->update(['must_change_password' => false]));
    $this->actingAs(tenantUser('cm2@equipe.test'), 'web')->get($this->host . '/equipe')->assertForbidden();

    asAdmin()->get($this->host . '/equipe')->assertOk()->assertSee('cm2@equipe.test')->assertSee('Chargé de communication');
    asAdmin()->get($this->host . '/equipe/roles-et-droits')->assertOk()
        ->assertSee('Valider une souscription')          // permission réelle du contrôleur-validateur
        ->assertSee('Agent d’adhésion ≠ Contrôleur-validateur', false);
});

it('impose le changement du mot de passe affiché lors de la création de la mutuelle', function () {
    $this->mutual->run(fn () => app(MutualProvisioningService::class)->createUser($this->mutual, 'nouveau@equipe.test', 'N', 'A'));
    expect(tenantUser('nouveau@equipe.test')->must_change_password)->toBeTrue();
});
