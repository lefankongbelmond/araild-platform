<?php

use App\Models\Central\Mutual;
use App\Models\Central\PublicAccount;
use App\Models\Social\MutualProfile;
use App\Models\Social\PageAdmin;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');

// --- Aides communes aux tests du réseau social (comptes, pages, projets, offres) ---

function account(string $name = 'Awa', bool $acceptLegal = true): PublicAccount
{
    $a = PublicAccount::create(['first_name' => $name, 'last_name' => 'Test', 'phone' => '+2376' . random_int(10000000, 99999999),
        'email' => Str::lower($name) . random_int(1000, 9999) . '@exemple.cm', 'password' => 'motdepasse-solide'])->fresh();
    // Comme à l'inscription : documents légaux en vigueur acceptés (2026-11-09).
    if ($acceptLegal) {
        app(\App\Services\Social\LegalService::class)->record($a, app(\App\Services\Social\LegalService::class)->current(), request(), 'web');
    }

    return $a;
}

/** Page créée par un membre (propriétaire = $owner). */
function memberPage(PublicAccount $owner, array $profile = []): Mutual
{
    $c = test()->country;
    $m = Mutual::withoutEvents(fn () => Mutual::create(['id' => 'pg_' . Str::lower(Str::random(10)), 'name' => 'Association ' . Str::random(4),
        'slug' => 'asso-' . Str::lower(Str::random(6)), 'country_id' => $c->id, 'currency_id' => $c->default_currency_id, 'locale_id' => $c->default_locale_id,
        'region' => 'Centre', 'city' => 'Yaoundé', 'status' => 'pending']));
    MutualProfile::create(['mutual_id' => $m->id, 'owner_account_id' => $owner->id, 'category' => 'osc'] + $profile);
    PageAdmin::create(['mutual_id' => $m->id, 'public_account_id' => $owner->id, 'role' => 'admin']);

    return $m->fresh('profile');
}

/** Mutuelle gérée sur MX-CONNECT (sans propriétaire de page). */
function mutualPage(): Mutual
{
    $c = test()->country;
    $m = Mutual::withoutEvents(fn () => Mutual::create(['id' => 'mut_' . Str::lower(Str::random(6)), 'name' => 'Mutuelle Ensemble',
        'slug' => 'ensemble-' . Str::lower(Str::random(4)), 'country_id' => $c->id, 'currency_id' => $c->default_currency_id, 'locale_id' => $c->default_locale_id,
        'region' => 'Littoral', 'city' => 'Douala', 'status' => 'approved']));
    MutualProfile::create(['mutual_id' => $m->id, 'category' => 'mutuelle']);

    return $m->fresh('profile');
}

function projectData(array $over = []): array
{
    return $over + ['title' => 'Dépistage du diabète', 'summary' => 'Dépistage gratuit dans dix villages du district.',
        'phase' => 'ongoing', 'status' => 'published', 'seeks_volunteers' => '1', 'volunteer_needs' => 'Accueil et orientation des personnes.',
        'seeks_partners' => '0', 'announce' => '1'];
}

function opportunityData(array $over = []): array
{
    return $over + ['category' => 'emploi', 'title' => 'Infirmier pour la tournée de dépistage', 'description' => str_repeat('Missions de dépistage et de sensibilisation. ', 3),
        'contract_type' => 'cdd', 'workplace' => 'site', 'region' => 'Centre', 'city' => 'Yaoundé', 'remuneration' => '150 000 XAF / mois',
        'apply_mode' => 'platform', 'status' => 'published', 'announce' => '1'];
}

function pdf(string $name = 'cv.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
}


// --- Aides communes aux tests du back-office : VRAIE base de mutuelle (mx_tests_tenant) ---

/**
 * Provisionne la mutuelle de test dans une base vide (comme une base créée dans le panneau LWS)
 * avec un administrateur qui a déjà choisi son mot de passe et activé la double authentification.
 */
function provisionTestMutual(): \App\Models\Central\Mutual
{
    test()->seed(\Database\Seeders\ConfigReferenceSeeder::class);
    $c = config('database.connections.central');
    $country = \App\Models\Central\Country::where('active', true)->firstOrFail();
    $base = ['name' => 'Mutuelle Équipe', 'slug' => 'equipe' . random_int(100, 999), 'country_id' => $country->id,
        'currency_id' => $country->default_currency_id, 'locale_id' => $country->default_locale_id];

    // MX_TEST_STORAGE=prefix : la même suite sur une mutuelle à TABLES PRÉFIXÉES dans la base
    // centrale (création automatique, 2026-11-02) ; sinon : base dédiée mx_tests_tenant.
    if (getenv('MX_TEST_STORAGE') === 'prefix') {
        config(['mxconnect.tenant_db_mode' => 'manual']);
        $pdo = new PDO("mysql:host={$c['host']};port={$c['port']};dbname={$c['database']}", $c['username'], $c['password']);
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach ($pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN) as $t) {
            if (preg_match('/^mut\d+_/', $t)) {
                $pdo->exec("DROP TABLE `{$t}`");   // tables d'une mutuelle d'un test précédent
            }
        }
        $mutual = app(\App\Services\MutualProvisioningService::class)->create($base + ['storage' => 'auto']);
        expect(\App\Support\TenantStorage::isPrefixed($mutual))->toBeTrue();
    } else {
        $pdo = new PDO("mysql:host={$c['host']};port={$c['port']};dbname=mx_tests_tenant", $c['username'], $c['password']);
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
            $pdo->exec("DROP TABLE `{$t}`");
        }
        $mutual = app(\App\Services\MutualProvisioningService::class)->create($base + [
            'db_name' => 'mx_tests_tenant', 'db_username' => $c['username'], 'db_password' => $c['password'],
        ]);
    }
    test()->mutual = $mutual;
    test()->host = 'http://' . $mutual->domains()->value('domain');
    staffUser('admin@equipe.test', ['mutual_admin']);

    return $mutual;
}

/** Membre du personnel prêt à l'emploi : mot de passe choisi, double authentification active. */
function staffUser(string $email, array $roles, array $extra = []): \App\Models\Tenant\User
{
    return test()->mutual->run(function () use ($email, $roles, $extra) {
        $u = \App\Models\Tenant\User::create($extra + ['first_name' => ucfirst(strtok($email, '@')), 'last_name' => 'Test', 'email' => $email,
            'password' => 'MotDePasse-Test-2026', 'active' => true, 'must_change_password' => false, 'two_factor_confirmed_at' => now()]);
        $u->syncRoles($roles);

        return $u;
    });
}

/** Utilisateur de la mutuelle de test (chargé dans la base de la mutuelle). */
function tenantUser(string $email): \App\Models\Tenant\User
{
    return test()->mutual->run(fn () => \App\Models\Tenant\User::with('roles')->where('email', $email)->firstOrFail());
}

/** Requêtes au nom d'un membre du personnel, double authentification passée. */
function as_(string $email): \Illuminate\Foundation\Testing\TestCase
{
    return test()->actingAs(tenantUser($email), 'web')->withSession(['mfa_passed' => true]);
}

function asAdmin(): \Illuminate\Foundation\Testing\TestCase
{
    return as_('admin@equipe.test');
}
