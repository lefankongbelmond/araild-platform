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

function account(string $name = 'Awa'): PublicAccount
{
    return PublicAccount::create(['first_name' => $name, 'last_name' => 'Test', 'phone' => '+2376' . random_int(10000000, 99999999),
        'email' => Str::lower($name) . random_int(1000, 9999) . '@exemple.cm', 'password' => 'motdepasse-solide'])->fresh();
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

