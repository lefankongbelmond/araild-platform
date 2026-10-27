<?php
// Données de test LOCALES pour la copie fidèle LWS (jamais pour la production) :
//  - mutuelle « mutueltest » provisionnée sur la base mx_mutueltest (mode manuel, comme sur LWS)
//    + un administrateur et un community manager ;
//  - 3 comptes membres, une page créée par un membre, des projets, résultats, offres et demandes.
// À chaque exécution, les mots de passe de ces comptes LOCAUX sont régénérés et écrits dans
// storage/app/comptes-locaux.txt (hors dépôt, hors ZIP). Le script peut être relancé sans dupliquer les données.
// Usage : docker exec -u application -w /repo/production/mxconnect mxlws-web php /repo/devtools/copie-lws/donnees-locales.php

use App\Models\Central\Country;
use App\Models\Central\Mutual;
use App\Models\Central\PublicAccount;
use App\Models\Social\MutualProfile;
use App\Models\Social\PageAdmin;
use App\Services\MutualProvisioningService;
use App\Services\Social\ProjectService;
use App\Services\Social\RequestService;
use Illuminate\Support\Str;

require '/repo/production/mxconnect/vendor/autoload.php';
$app = require '/repo/production/mxconnect/bootstrap/app.php';
$app->usePublicPath('/repo/production');
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

@unlink('/repo/production/mxconnect/storage/app/comptes-locaux.txt');
$note = fn (string $line) => file_put_contents('/repo/production/mxconnect/storage/app/comptes-locaux.txt', $line . "\n", FILE_APPEND);
$country = Country::where('iso2', 'CM')->first() ?? Country::where('active', true)->firstOrFail();

// --- Mutuelle de test (base pré-créée, comme dans le panneau LWS) ---
$mutual = Mutual::where('slug', 'mutueltest')->first();
$prov = app(MutualProvisioningService::class);
if (! $mutual) {
    $db = config('database.connections.central');
    $mutual = $prov->create([
        'name' => 'Mutuelle Test', 'slug' => 'mutueltest', 'country_id' => $country->id,
        'currency_id' => $country->default_currency_id, 'locale_id' => $country->default_locale_id,
        'region' => 'Centre', 'city' => 'Yaoundé',
        'db_name' => 'mx_mutueltest', 'db_username' => $db['username'], 'db_password' => $db['password'],
    ]);
}
$note('Back-office http://mutueltest.m.araild.test:8080/connexion');
$note('  admin@mutueltest.test / ' . $prov->createUser($mutual, 'admin@mutueltest.test', 'Admin', 'Test'));
$note('  cm@mutueltest.test / ' . $prov->createUser($mutual, 'cm@mutueltest.test', 'Chargé', 'Communication', 'community_manager'));
MutualProfile::updateOrCreate(['mutual_id' => $mutual->id], ['category' => 'mutuelle', 'tagline' => 'Mutuelle de santé communautaire (test local)', 'domains' => ['sante', 'protection_sociale']]);

// --- Comptes membres ---
$mk = function (string $first, string $email) use ($note) {
    $pwd = Str::password(14, symbols: false);
    $acc = PublicAccount::where('email', $email)->first();
    if ($acc) {
        $acc->update(['password' => $pwd]);
    } else {
        $acc = PublicAccount::create(['first_name' => $first, 'last_name' => 'Local', 'email' => $email,
            'phone' => '+2376' . random_int(10000000, 99999999), 'password' => $pwd]);
    }
    $note("Membre {$email} / {$pwd}");

    return $acc->fresh();
};
$owner = $mk('Aïcha', 'aicha@membre.test');
$candidate = $mk('Jean', 'jean@membre.test');
$partner = $mk('Clarisse', 'clarisse@membre.test');

// --- Page créée par un membre ---
$page = Mutual::where('slug', 'sante-pour-tous-local')->first();
if (! $page) {
    $page = Mutual::withoutEvents(fn () => Mutual::create(['id' => 'pg_' . Str::lower(Str::random(10)), 'name' => 'Association Santé pour Tous',
        'slug' => 'sante-pour-tous-local', 'country_id' => $country->id, 'currency_id' => $country->default_currency_id,
        'locale_id' => $country->default_locale_id, 'region' => 'Centre', 'city' => 'Mbalmayo', 'status' => 'pending']));
    MutualProfile::create(['mutual_id' => $page->id, 'owner_account_id' => $owner->id, 'category' => 'osc', 'domains' => ['sante', 'solidarite'],
        'tagline' => 'Prévention et accès aux soins en zone rurale']);
    PageAdmin::create(['mutual_id' => $page->id, 'public_account_id' => $owner->id, 'role' => 'admin']);
    $page = $page->fresh('profile');

    $svc = app(ProjectService::class);
    $project = $svc->saveProject($page, ['title' => 'Dépistage du diabète dans 10 villages', 'summary' => 'Dépistage gratuit et orientation vers les centres de santé du district de Mbalmayo.',
        'description' => "Tournées mensuelles de dépistage de la glycémie, conseils nutritionnels et orientation.\nPartenariat avec le district de santé.",
        'objectives' => 'Dépister 2 000 adultes et orienter les cas positifs.', 'zone' => 'District de santé de Mbalmayo', 'region' => 'Centre', 'city' => 'Mbalmayo',
        'phase' => 'ongoing', 'starts_on' => '2026-03-01', 'ends_on' => '2026-12-31', 'seeks_volunteers' => 1, 'volunteer_needs' => 'Accueil, orientation et sensibilisation les samedis.',
        'seeks_partners' => 1, 'partner_needs' => 'Bandelettes de glycémie, transport des équipes.', 'status' => 'published', 'announce' => 1,
        'progress' => 62, 'beneficiaries' => '10 villages, environ 3 000 adultes', 'partners' => "District de santé de Mbalmayo\nPharmacie du Centre", 'domains' => ['sante']], null, null, $owner);
    app(\App\Services\Social\PostService::class)->create(['body' => 'Septième village couvert par la tournée de dépistage ! Merci aux bénévoles #SantéCommunautaire #Prévention', 'type' => 'activite'], [], $owner, $page);
    $svc->saveIndicator($project, ['label' => 'Adultes dépistés', 'value' => 1250, 'target' => 2000, 'unit' => 'personnes', 'period' => 'Mars–septembre 2026', 'source_note' => 'Registres de dépistage du district']);
    $svc->saveIndicator($project, ['label' => 'Villages couverts', 'value' => 7, 'target' => 10, 'unit' => 'villages', 'source_note' => 'Rapports de tournée']);
    $svc->addUpdate($project, 'Septième village couvert', 'La tournée de septembre a couvert le village de Nkolmebanga.', false, $page, $owner);

    $job = $svc->saveOpportunity($page, ['category' => 'emploi', 'title' => 'Infirmier(ère) pour la tournée de dépistage', 'description' => "Participation aux tournées mensuelles de dépistage.\nDéplacements dans le district.",
        'requirements' => 'Diplôme d’infirmier, permis apprécié.', 'contract_type' => 'cdd', 'workplace' => 'site', 'region' => 'Centre', 'city' => 'Mbalmayo', 'country_id' => $country->id,
        'remuneration' => '150 000 XAF / mois', 'positions' => 2, 'deadline_on' => now()->addMonth()->toDateString(), 'apply_mode' => 'platform', 'project_id' => $project->id,
        'status' => 'published', 'announce' => 1], null, $owner);
    $svc->saveOpportunity($page, ['category' => 'benevolat', 'title' => 'Bénévoles pour la journée santé du 15 novembre', 'description' => 'Accueil du public, orientation, animation des stands de prévention.',
        'contract_type' => 'benevolat', 'workplace' => 'site', 'region' => 'Centre', 'city' => 'Mbalmayo', 'apply_mode' => 'platform', 'status' => 'published'], null, $owner);
    $svc->saveOpportunity($page, ['category' => 'appel_partenaires', 'title' => 'Appel à partenaires : transport des équipes', 'description' => 'Nous cherchons un partenaire pour le transport des équipes de dépistage.',
        'workplace' => 'site', 'region' => 'Centre', 'apply_mode' => 'external', 'apply_url' => 'https://example.org/partenaires', 'status' => 'published'], null, $owner);

    app(RequestService::class)->submit('application', $page, $candidate, ['message' => 'Infirmier diplômé, trois ans d’expérience en dépistage communautaire.',
        'contact_name' => 'Jean Local', 'contact_phone' => '+237690000001'], null, $job);
    app(RequestService::class)->submit('support', $page, $partner, ['subtype' => 'materiel', 'organisation_name' => 'Pharmacie du Centre',
        'message' => 'Nous pouvons offrir 200 bandelettes de glycémie pour la prochaine tournée.', 'contact_name' => 'Clarisse Local', 'contact_email' => 'clarisse@membre.test'], $project);
}

// --- Offre et projet de la mutuelle de test (publiés comme depuis son back-office) ---
if (! \App\Models\Social\Opportunity::where('mutual_id', $mutual->id)->exists()) {
    $mutual->load('profile');
    app(ProjectService::class)->saveOpportunity($mutual, ['category' => 'stage', 'title' => 'Stage : agent d’adhésion (3 mois)', 'description' => 'Accueil des adhérents, saisie des dossiers, relances de cotisations.',
        'contract_type' => 'stage', 'workplace' => 'site', 'region' => 'Centre', 'city' => 'Yaoundé', 'apply_mode' => 'platform', 'status' => 'published', 'announce' => 1], null, null, null, 'Mutuelle Test');
    app(RequestService::class)->submit('engagement', $mutual, $partner, ['subtype' => 'relais', 'message' => 'Je souhaite devenir relais communautaire de la mutuelle dans mon quartier.',
        'contact_name' => 'Clarisse Local', 'contact_email' => 'clarisse@membre.test']);
}

// --- Lot 7 : un compte par rôle, un prestataire conventionné, un adhérent couvert ---
foreach ([['tresorier', 'Trésor', 'Ier', 'treasurer_accountant'], ['auditeur', 'Audit', 'Eur', 'auditor'], ['gestionnaire', 'Gest', 'Ionnaire', 'benefits_manager'],
          ['controleur', 'Contrôle', 'Eur', 'controller_validator'], ['agent', 'Paul', 'Agent', 'enrollment_agent']] as [$login, $first, $last, $role]) {
    $note("  {$login}@mutueltest.test / " . $prov->createUser($mutual, "{$login}@mutueltest.test", $first, $last, $role));
}
$csi = \App\Models\Central\CareProvider::firstOrCreate(['name' => 'Centre de santé local de Mvog-Ada'],
    ['type' => 'health_center', 'country_id' => $country->id, 'locality' => 'Yaoundé', 'active' => true, 'created_by_mutual' => (string) $mutual->id]);
$note('  prestataire@mutueltest.test / ' . $prov->createUser($mutual, 'prestataire@mutueltest.test', 'Accueil', 'Mvog-Ada', 'provider'));
$mutual->run(function () use ($csi) {
    \App\Models\Tenant\ProviderConvention::firstOrCreate(['care_provider_id' => $csi->id], ['reference' => 'CONV-2026-01', 'starts_on' => '2026-01-01', 'active' => true]);
    \App\Models\Tenant\User::where('email', 'prestataire@mutueltest.test')->update(['care_provider_id' => $csi->id]);
    $g = \App\Models\Tenant\Guarantee::firstOrCreate(['code' => 'LOC-ESS'], ['name' => 'Essentielle (local)', 'status' => 'active']);
    $v = \App\Models\Tenant\GuaranteeVersion::firstOrCreate(['guarantee_id' => $g->id, 'version_no' => 1], ['valid_from' => '2026-01-01', 'base_contribution_minor' => 1500,
        'periodicity' => 'monthly', 'coverage_rate' => 80, 'copay_rate' => 20, 'observation_days' => 0]);
    $m = \App\Models\Tenant\Member::firstOrCreate(['member_code' => 'MUT-LOCAL-001'], ['first_name' => 'Awa', 'last_name' => 'Nkolo', 'birth_date' => '1990-04-02',
        'sex' => 'F', 'status' => 'active', 'joined_at' => '2026-01-01']);
    $sub = \App\Models\Tenant\Subscription::firstOrCreate(['member_id' => $m->id, 'guarantee_version_id' => $v->id], ['contribution_minor' => 1500, 'effective_date' => '2026-01-01',
        'observation_ends_on' => '2026-01-01', 'status' => 'validated', 'captured_by' => 1]);
    // Lot 13 : « couvert » = à jour de ses cotisations. Échéancier glissant, échéances déjà
    // arrivées soldées par un encaissement LOCAL (sinon les droits sont, à raison, refusés).
    app(\App\Services\ScheduleGenerationService::class)->extend($sub->fresh('guaranteeVersion'));
    $due = \App\Models\Tenant\ContributionSchedule::where('subscription_id', $sub->id)->where('status', '!=', 'paid')
        ->whereDate('due_date', '<=', now()->toDateString())->withSum('payments', 'amount_minor')->get()
        ->sum(fn ($s) => max(0, $s->due_minor - (int) $s->payments_sum_amount_minor));
    if ($due > 0) {
        app(\App\Services\Tenant\ContributionCollectionService::class)->collect($m, $due, now()->toDateString(), 'cash', 'DONNEES-LOCALES', 1);
    }
    \App\Models\Tenant\ActType::firstOrCreate(['code' => 'LOC-CONS'], ['label' => 'Consultation de médecine générale', 'category' => 'consultation']);
    // Comptes LOCAUX prêts pour le parcours navigateur : mot de passe déjà choisi, double authentification à (re)configurer.
    \App\Models\Tenant\User::where('email', 'like', '%@mutueltest.test')->update(['must_change_password' => false,
        'two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null]);
});
$note('Adhérent couvert : code MUT-LOCAL-001 (prestataire : Centre de santé local de Mvog-Ada)');

// --- Lot 9 : super-admin du réseau (LOCAL) : mot de passe régénéré, double authentification à reconfigurer ---
$sa = \App\Models\Central\NetworkUser::where('email', 'admin@mx-connect.com')->first();
if ($sa) {
    $pwd = Str::password(16, symbols: false);
    $sa->forceFill(['password' => \Illuminate\Support\Facades\Hash::make($pwd), 'mfa_secret' => null, 'mfa_enabled' => false, 'active' => true])->save();
    $note("Réseau http://m.araild.test:8080/reseau/connexion\n  admin@mx-connect.com / {$pwd}");
}

echo "Données locales prêtes. Comptes : production/mxconnect/storage/app/comptes-locaux.txt\n";
