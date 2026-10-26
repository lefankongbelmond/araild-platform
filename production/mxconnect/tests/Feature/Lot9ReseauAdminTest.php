<?php

/*
 * Lot 9 — espace super-admin : création AUTOMATIQUE des mutuelles (tables préfixées dans la
 * base centrale, sans base à créer dans le panneau LWS), suspension réelle et réactivation,
 * pays de la liste de référence, nouvelle mise en page.
 */

use App\Models\Central\Country;
use App\Models\Central\Currency;
use App\Models\Central\Mutual;
use App\Models\Central\NetworkUser;
use App\Models\Tenant\Member;
use App\Services\BackupService;
use App\Services\MutualProvisioningService;
use App\Support\TenantStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(\Database\Seeders\ConfigReferenceSeeder::class);
    config(['mxconnect.tenant_db_mode' => 'manual']);   // hébergement mutualisé (LWS)
    dropPrefixedTables();
    $this->country = Country::where('iso2', 'CM')->firstOrFail();
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
    dropPrefixedTables();
});

/** Tables « mutNN_… » laissées par un test (créées par une autre connexion, donc hors transaction). */
function dropPrefixedTables(): void
{
    $db = DB::connection('central');
    $db->statement('SET FOREIGN_KEY_CHECKS=0');
    foreach ($db->select('SHOW TABLES') as $row) {
        $t = array_values((array) $row)[0];
        if (preg_match('/^mut\d+_/', $t)) {
            $db->statement("DROP TABLE `{$t}`");
        }
    }
    $db->statement('SET FOREIGN_KEY_CHECKS=1');
}

function networkUser(string $role = 'super_admin'): NetworkUser
{
    if (tenancy()->initialized) {
        tenancy()->end();   // une requête précédente vers un sous-domaine a laissé le contexte mutuelle actif (processus de test)
    }
    Role::findOrCreate($role, 'network');
    $u = NetworkUser::factory()->create(['mfa_enabled' => true]);
    $u->assignRole($role);

    return $u;
}

function asNetwork(NetworkUser $u): \Illuminate\Foundation\Testing\TestCase
{
    return test()->actingAs($u, 'network')->withSession(['mfa_passed' => true]);
}

function autoMutual(string $slug): Mutual
{
    $c = test()->country;

    return app(MutualProvisioningService::class)->create(['name' => 'Mutuelle ' . $slug, 'slug' => $slug, 'country_id' => $c->id,
        'currency_id' => $c->default_currency_id, 'locale_id' => $c->default_locale_id, 'storage' => 'auto']);
}

it('crée une mutuelle sans aucune base à préparer : tables préfixées, rôles, plan comptable, administrateur', function () {
    $sa = networkUser();
    asNetwork($sa)->get('/reseau/mutuelles/creer')->assertOk()->assertSee('Automatique (recommandé)')->assertSee('Option avancée');

    $res = asNetwork($sa)->post('/reseau/mutuelles', ['name' => 'Mutuelle d’Akono', 'slug' => 'akono', 'country_id' => $this->country->id,
        'currency_id' => $this->country->default_currency_id, 'locale_id' => $this->country->default_locale_id, 'storage' => 'auto',
        'admin_email' => 'Admin@Akono.test', 'admin_first_name' => 'Awa', 'admin_last_name' => 'Ngo']);
    $m = Mutual::where('slug', 'akono')->firstOrFail();
    $res->assertRedirect(route('network.mutuals.show', $m));

    expect(TenantStorage::isPrefixed($m))->toBeTrue()
        ->and($m->getInternal('db_name'))->toBe(config('database.connections.central.database'))
        ->and(count(TenantStorage::tables(TenantStorage::prefix($m))))->toBeGreaterThan(40)
        ->and($m->status)->toBe('pending');
    [$email, $password] = session('admin_password');
    expect($email)->toBe('admin@akono.test')->and(strlen($password))->toBe(16);
    $m->run(function () {
        expect(DB::table('roles')->pluck('name')->all())->toContain('mutual_admin', 'auditor')
            ->and(DB::table('accounts')->count())->toBeGreaterThan(0)
            ->and(\App\Models\Tenant\User::where('email', 'admin@akono.test')->first()->must_change_password)->toBeTrue();
    });
    // La fiche montre le stockage et le mot de passe une seule fois.
    asNetwork($sa)->get(route('network.mutuals.show', $m))->assertOk()->assertSee('Tables préfixées')->assertSee($password);
    asNetwork($sa)->get(route('network.mutuals.show', $m))->assertOk()->assertDontSee($password);

    // L'option avancée (base déjà créée dans le panneau) reste disponible et vérifiée.
    asNetwork($sa)->post('/reseau/mutuelles', ['name' => 'B', 'slug' => 'bbb', 'country_id' => $this->country->id,
        'currency_id' => $this->country->default_currency_id, 'locale_id' => $this->country->default_locale_id, 'storage' => 'existing'])
        ->assertSessionHasErrors('db_name');
});

it('isole les mutuelles à tables préfixées entre elles (données et sauvegardes)', function () {
    $a = autoMutual('alpha');
    $b = autoMutual('beta');
    expect(TenantStorage::prefix($a))->not->toBe(TenantStorage::prefix($b));

    $a->run(fn () => Member::create(['member_code' => 'A-001', 'first_name' => 'Awa', 'last_name' => 'Alpha', 'birth_date' => '1990-01-01',
        'sex' => 'F', 'status' => 'active', 'joined_at' => '2026-01-01']));
    expect($a->run(fn () => Member::count()))->toBe(1)
        ->and($b->run(fn () => Member::count()))->toBe(0)
        ->and($b->run(fn () => DB::table('members')->where('member_code', 'A-001')->exists()))->toBeFalse()
        ->and($a->run(fn () => DB::connection()->getTablePrefix()))->toBe(TenantStorage::prefix($a));

    // Sauvegarde d'une mutuelle à préfixe : SES tables seulement (ni l'autre mutuelle, ni le réseau).
    $dr = app(BackupService::class)->run();
    $dir = collect(File::directories(storage_path('app/backups')))->sort()->last();
    $sql = File::get($dir . '/tenant_' . $a->id . '.sql');
    expect($sql)->toContain('`' . TenantStorage::prefix($a) . 'members`')->toContain('A-001')
        ->not->toContain('`' . TenantStorage::prefix($b) . 'members`')->not->toContain('`public_accounts`');
    File::deleteDirectory($dir);
});

it('ne touche jamais aux tables du réseau en supprimant une mutuelle à préfixe', function () {
    $a = autoMutual('gamma');
    $prefix = TenantStorage::prefix($a);
    (new \App\Jobs\Tenancy\DeleteTenantDatabase($a))->handle();
    expect(TenantStorage::tables($prefix))->toBe([])
        ->and(DB::connection('central')->getSchemaBuilder()->hasTable('mutuals'))->toBeTrue()
        ->and(DB::connection('central')->getSchemaBuilder()->hasTable('public_accounts'))->toBeTrue();
    expect(fn () => TenantStorage::dropTables(''))->toThrow(InvalidArgumentException::class);
});

it('suspend réellement une mutuelle (motif obligatoire) puis la réactive', function () {
    $sa = networkUser();
    $m = autoMutual('delta');
    $host = 'http://' . $m->domains()->value('domain');

    asNetwork($sa)->post(route('network.mutuals.suspend', $m), ['reason' => ''])->assertSessionHasErrors('reason');
    asNetwork($sa)->post(route('network.mutuals.suspend', $m), ['reason' => 'Cotisation SaaS impayée'])->assertSessionHas('success');
    $m->refresh();
    expect($m->status)->toBe('suspended')->and($m->suspension_reason)->toBe('Cotisation SaaS impayée');

    $this->get($host . '/connexion')->assertForbidden()->assertSee('Espace suspendu');
    $this->postJson($host . '/api/member/login', ['login' => 'x', 'password' => 'y'])->assertForbidden()->assertJson(['suspended' => true]);
    $this->get($host . '/espace-membre')->assertForbidden();

    $mod = networkUser('moderator');
    asNetwork($mod)->post(route('network.mutuals.reactivate', $m))->assertForbidden();

    asNetwork($sa)->post(route('network.mutuals.reactivate', $m))->assertSessionHas('success');
    expect($m->refresh()->status)->toBe('pending');           // jamais agréée : retour « en attente »
    $this->get($host . '/connexion')->assertOk();

    asNetwork($sa)->post(route('network.mutuals.approve', $m));
    asNetwork($sa)->post(route('network.mutuals.suspend', $m), ['reason' => 'Contrôle']);
    asNetwork($sa)->post(route('network.mutuals.reactivate', $m));
    expect($m->refresh()->status)->toBe('approved');          // déjà agréée : retour « agréée »
    asNetwork($sa)->get(route('network.mutuals.show', $m))->assertOk()
        ->assertSee('Suspendue')->assertSee('Réactivée')->assertSee('Cotisation SaaS impayée');
});

it('rétablit l’accès d’un administrateur de mutuelle (nouveau mot de passe, double authentification à refaire)', function () {
    $sa = networkUser();
    $m = autoMutual('epsilon');
    app(MutualProvisioningService::class)->createUser($m, 'chef@epsilon.test', 'Chef', 'E');
    $m->run(fn () => \App\Models\Tenant\User::where('email', 'chef@epsilon.test')->update(['two_factor_confirmed_at' => now(), 'must_change_password' => false]));

    asNetwork($sa)->post(route('network.mutuals.admin', $m), ['admin_email' => 'chef@epsilon.test'])->assertSessionHas('admin_password');
    [, $password, , $reset] = session('admin_password');
    expect($reset)->toBeTrue();
    $u = $m->run(fn () => \App\Models\Tenant\User::where('email', 'chef@epsilon.test')->first());
    expect(\Illuminate\Support\Facades\Hash::check($password, $u->password))->toBeTrue()
        ->and($u->two_factor_confirmed_at)->toBeNull()->and($u->must_change_password)->toBeTrue();
    asNetwork(networkUser('security_admin'))->post(route('network.mutuals.admin', $m), ['admin_email' => 'x@epsilon.test'])->assertForbidden();
});

it('ajoute des pays depuis la liste de référence, avec leurs devises, sans modifier l’existant', function () {
    $sa = networkUser();
    $before = $this->country->only(['name', 'default_currency_id']);
    asNetwork($sa)->get('/reseau/configuration/pays')->assertOk()->assertSee('Afrique de l’Ouest', false)->assertSee('Seychelles');

    asNetwork($sa)->post(route('network.config.countries.catalog'), ['iso2' => ['SN', 'SC', 'CM']])->assertSessionHas('success');
    expect(Country::where('iso2', 'SN')->first()->defaultCurrency->code)->toBe('XOF')
        ->and(Currency::where('code', 'SCR')->value('minor_unit'))->toBe(2)
        ->and(Country::where('iso2', 'SC')->first()->phone_prefix)->toBe('+248')
        ->and(Country::where('iso2', 'CM')->first()->only(['name', 'default_currency_id']))->toBe($before);

    $sn = Country::where('iso2', 'SN')->first();
    asNetwork($sa)->post(route('network.config.countries.toggle', $sn));
    expect($sn->fresh()->active)->toBeFalse();
    asNetwork($sa)->get('/reseau/mutuelles/creer')->assertDontSee('>Sénégal<', false);

    asNetwork(networkUser('security_admin'))->post(route('network.config.countries.catalog'), ['iso2' => ['GA']])->assertForbidden();
});

it('présente l’espace super-admin avec la nouvelle mise en page et des rubriques selon le rôle', function () {
    $sa = networkUser();
    autoMutual('zeta');
    asNetwork($sa)->get('/reseau/mutuelles')->assertOk()
        ->assertSee('/css/mx-network.css', false)->assertDontSee('cdn.tailwindcss.com', false)
        ->assertSee('Administration du réseau')->assertSee('Nouvelle mutuelle')->assertSee('Mutuelle zeta')->assertSee('Plans et tarifs');
    asNetwork($sa)->get('/reseau/mutuelles?statut=suspended')->assertOk()->assertDontSee('Mutuelle zeta');

    asNetwork(networkUser('security_admin'))->get('/reseau/mutuelles')->assertOk()->assertDontSee('+ Nouvelle mutuelle')->assertDontSee('Plans et tarifs');
    asNetwork(networkUser('moderator'))->get('/reseau/mutuelles')->assertForbidden();
});
