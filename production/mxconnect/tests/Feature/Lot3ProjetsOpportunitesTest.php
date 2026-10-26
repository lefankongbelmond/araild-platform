<?php

/*
 * Lot 3 — projets (résultats déclarés / vérifiés), emplois et opportunités, demandes
 * (« Je m'engage », « Soutenir ou devenir partenaire », candidatures), pour les
 * mutuelles ET les pages créées par les membres.
 */

use App\Models\Central\Country;
use App\Models\Central\Mutual;
use App\Models\Central\NetworkUser;
use App\Models\Central\PublicAccount;
use App\Models\Social\MutualProfile;
use App\Models\Social\Opportunity;
use App\Models\Social\PageAdmin;
use App\Models\Social\Post;
use App\Models\Social\Project;
use App\Models\Social\ProjectIndicator;
use App\Models\Social\SocialNotification;
use App\Models\Social\SocialRequest;
use App\Services\Social\ProjectService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake('social');
    Storage::fake('social_private');
    $this->seed(\Database\Seeders\ConfigReferenceSeeder::class);   // pays, devises, langues de référence
    $this->country = Country::where('active', true)->firstOrFail();
});

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

// --- Projets ---

it('permet au créateur d’une page de membre de publier un projet, annoncé dans le fil', function () {
    $owner = account();
    $page = memberPage($owner);

    $this->actingAs($owner, 'member')->post(route('social.manage.project.store', $page->slug), projectData())
        ->assertRedirect();

    $project = Project::firstOrFail();
    expect($project->mutual_id)->toBe($page->id)->and($project->created_by_account_id)->toBe($owner->id)
        ->and($project->published_at)->not->toBeNull();
    $post = Post::where('project_id', $project->id)->first();
    expect($post)->not->toBeNull()->and($post->mutual_id)->toBe($page->id);

    $this->get(route('social.post', $post))->assertOk()->assertSee('Voir le projet et les résultats');
    $this->get(route('social.projects'))->assertOk()->assertSee('Dépistage du diabète');
    $this->get(route('social.mutual', [$page->slug, 'onglet' => 'projets']))->assertOk()->assertSee('Dépistage du diabète');
});

it('affiche les résultats « Déclaré » puis « Vérifié », et annule la vérification si le chiffre change', function () {
    $owner = account();
    $page = memberPage($owner);
    $this->actingAs($owner, 'member')->post(route('social.manage.project.store', $page->slug), projectData());
    $project = Project::firstOrFail();

    $this->post(route('social.manage.indicator.store', [$page->slug, $project->id]),
        ['label' => 'Personnes dépistées', 'value' => '1250', 'target' => '2000', 'unit' => 'personnes', 'source_note' => 'Registres du district'])
        ->assertRedirect();
    $i = ProjectIndicator::firstOrFail();
    $this->get(route('social.project', $project))->assertOk()->assertSee('1 250')->assertSee('Déclaré')->assertSee('63 %');

    $admin = NetworkUser::factory()->create();
    app(ProjectService::class)->verifyIndicator($i, $admin, 'Rapport du district vérifié');
    $this->get(route('social.project', $project))->assertSee('Vérifié');
    expect(SocialNotification::where('public_account_id', $owner->id)->where('type', 'resultat')->exists())->toBeTrue();

    // La page modifie le chiffre : il redevient « déclaré ».
    $this->post(route('social.manage.indicator.update', [$page->slug, $project->id, $i->id]),
        ['label' => 'Personnes dépistées', 'value' => '1400', 'unit' => 'personnes'])->assertRedirect();
    expect($i->fresh()->verified_at)->toBeNull();
    // Libellé de source seul modifié : la vérification resterait (aucun chiffre changé).
});

it('n’autorise que l’administration du réseau à vérifier un résultat', function () {
    $owner = account();
    $page = memberPage($owner);
    $project = app(ProjectService::class)->saveProject($page, projectData(['announce' => null]), null, null, $owner);
    $i = app(ProjectService::class)->saveIndicator($project, ['label' => 'Villages couverts', 'value' => 10]);

    Role::findOrCreate('moderator', 'network');
    $mod = NetworkUser::factory()->create(['mfa_enabled' => true]);
    $mod->assignRole('moderator');

    // Un membre (même gestionnaire) n'a aucune route pour vérifier.
    $this->actingAs($owner, 'member')->post('/reseau/reseau-social/resultats/' . $i->id, ['action' => 'verifier', 'note' => 'x'])
        ->assertRedirect();   // renvoyé vers la connexion réseau
    expect($i->fresh()->verified_at)->toBeNull();

    $this->actingAs($mod, 'network')->withSession(['mfa_passed' => true])
        ->post(route('network.social.indicators.verify', $i->id), ['action' => 'verifier'])->assertSessionHasErrors('note');
    $this->post(route('network.social.indicators.verify', $i->id), ['action' => 'verifier', 'note' => 'Rapport d’activité contrôlé'])
        ->assertSessionHas('success');
    expect($i->fresh()->verified_at)->not->toBeNull();
});

it('garde les brouillons privés et empêche une page de lever un masquage de modération', function () {
    $owner = account();
    $page = memberPage($owner);
    $this->actingAs($owner, 'member')->post(route('social.manage.project.store', $page->slug), projectData(['status' => 'draft']));
    $project = Project::firstOrFail();
    expect(Post::where('project_id', $project->id)->exists())->toBeFalse();   // pas d'annonce pour un brouillon

    $this->get(route('social.project', $project))->assertOk()->assertSee('Brouillon');
    auth('member')->logout();
    $this->get(route('social.project', $project))->assertNotFound();
    $this->get(route('social.projects'))->assertDontSee('Dépistage du diabète');

    $project->update(['status' => 'hidden']);
    $this->actingAs($owner, 'member')->post(route('social.manage.project.update', [$page->slug, $project->id]), projectData(['status' => 'published']));
    expect($project->fresh()->status)->toBe('hidden');
});

it('réserve la gestion aux gestionnaires et la boîte de réception aux administrateurs', function () {
    $owner = account();
    $page = memberPage($owner);
    $editor = account('Paul');
    PageAdmin::create(['mutual_id' => $page->id, 'public_account_id' => $editor->id, 'role' => 'editor']);
    $stranger = account('Inconnu');

    $this->actingAs($stranger, 'member')->get(route('social.manage.projects', $page->slug))->assertForbidden();
    $this->actingAs($stranger, 'member')->post(route('social.manage.project.store', $page->slug), projectData())->assertForbidden();

    $this->actingAs($editor, 'member')->get(route('social.manage.projects', $page->slug))->assertOk();
    $this->actingAs($editor, 'member')->post(route('social.manage.opportunity.store', $page->slug), opportunityData())->assertRedirect();
    $this->actingAs($editor, 'member')->get(route('social.manage.requests', $page->slug))->assertForbidden();
    $this->actingAs($owner, 'member')->get(route('social.manage.requests', $page->slug))->assertOk();
});

// --- Opportunités et recherche ---

it('publie des offres depuis une page de membre et les retrouve par la recherche', function () {
    $owner = account();
    $page = memberPage($owner);
    $svc = app(ProjectService::class);
    $svc->saveOpportunity($page, opportunityData(['announce' => null]), null, $owner);
    $svc->saveOpportunity($page, opportunityData(['category' => 'benevolat', 'title' => 'Bénévoles pour la journée santé', 'contract_type' => 'benevolat', 'region' => 'Ouest', 'announce' => null]), null, $owner);
    $svc->saveOpportunity($page, opportunityData(['title' => 'Comptable (offre expirée)', 'deadline_on' => now()->subDay()->toDateString(), 'announce' => null]), null, $owner);
    $filled = $svc->saveOpportunity($page, opportunityData(['title' => 'Chauffeur (poste pourvu)', 'announce' => null]), null, $owner);
    $svc->setAvailability($filled, 'filled');
    $svc->saveOpportunity($page, opportunityData(['title' => 'Brouillon secret', 'status' => 'draft', 'announce' => null]), null, $owner);

    $this->get(route('social.opportunities'))->assertOk()
        ->assertSee('Infirmier pour la tournée')->assertSee('Bénévoles pour la journée')
        ->assertDontSee('Comptable (offre expirée)')->assertDontSee('Chauffeur (poste pourvu)')->assertDontSee('Brouillon secret');
    $this->get(route('social.opportunities', ['categorie' => 'emplois']))->assertSee('Infirmier')->assertDontSee('Bénévoles pour la journée');
    $this->get(route('social.opportunities', ['q' => 'journée santé']))->assertSee('Bénévoles pour la journée')->assertDontSee('Infirmier');
    $this->get(route('social.opportunities', ['region' => 'Ouest']))->assertSee('Bénévoles')->assertDontSee('Infirmier');
    $this->get(route('social.opportunities', ['contrat' => 'cdd']))->assertSee('Infirmier')->assertDontSee('Bénévoles pour');
    $this->get(route('social.opportunities', ['toutes' => 1]))->assertSee('Comptable (offre expirée)')->assertSee('Chauffeur (poste pourvu)')->assertDontSee('Brouillon secret');
    $this->get(route('social.opportunities', ['q' => $page->name]))->assertSee('Infirmier');   // recherche par organisation
    $this->get(route('social.mutual', [$page->slug, 'onglet' => 'opportunites']))->assertOk()->assertSee('Infirmier');
});

it('annonce une offre dans le fil avec un accès direct à l’offre', function () {
    $owner = account();
    $page = memberPage($owner);
    $this->actingAs($owner, 'member')->post(route('social.manage.opportunity.store', $page->slug), opportunityData())->assertRedirect();
    $o = Opportunity::firstOrFail();
    $post = Post::where('opportunity_id', $o->id)->firstOrFail();
    expect($post->type)->toBe('annonce');
    $this->get(route('social.post', $post))->assertSee('Voir l’offre');
});

// --- Candidatures, engagement, soutien ---

it('reçoit une candidature avec CV privé, notifie la page et bloque les doublons', function () {
    $owner = account();
    $page = memberPage($owner);
    $o = app(ProjectService::class)->saveOpportunity($page, opportunityData(['announce' => null]), null, $owner);
    $candidate = account('Candidat');

    $this->actingAs($candidate, 'member')->get(route('social.request.create', ['type' => 'application', 'offre' => $o->slug]))->assertOk()->assertSee('Postuler');
    $this->post(route('social.request.store'), [
        'type' => 'application', 'offre' => $o->slug, 'message' => 'Infirmier diplômé, trois ans de terrain en dépistage communautaire.',
        'contact_name' => 'Candidat Test', 'contact_phone' => '00237 690 00 00 00', 'cv' => pdf(), 'consent' => '1', 'no_medical' => '1',
    ])->assertRedirect();

    $r = SocialRequest::firstOrFail();
    expect($r->kind)->toBe('application')->and($r->status)->toBe('submitted')->and($r->contact_phone)->toBe('+237690000000')
        ->and(Str::startsWith($r->reference, 'CAN-'))->toBeTrue();
    Storage::disk('social_private')->assertExists($r->cv_path);
    expect($o->fresh()->requests_count)->toBe(1);
    expect(SocialNotification::where('public_account_id', $owner->id)->where('type', 'demande')->exists())->toBeTrue();

    // Doublon refusé tant que la première candidature est ouverte.
    $this->post(route('social.request.store'), ['type' => 'application', 'offre' => $o->slug, 'message' => str_repeat('Motivation ', 4),
        'contact_name' => 'Candidat', 'contact_email' => 'c@exemple.cm', 'consent' => '1', 'no_medical' => '1'])->assertRedirect();
    expect(SocialRequest::count())->toBe(1);

    // Le CV : lisible par le candidat et la page, pas par un tiers.
    $this->get(route('social.request.cv', $r->reference))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    $this->actingAs($owner, 'member')->get(route('social.manage.request.cv', [$page->slug, $r->reference]))->assertOk();
    $this->actingAs(account('Curieux'), 'member')->get(route('social.request.cv', $r->reference))->assertNotFound();
});

it('refuse un CV qui n’est pas un PDF et exige les consentements', function () {
    $page = memberPage(account());
    $o = app(ProjectService::class)->saveOpportunity($page, opportunityData(['announce' => null]));
    $this->actingAs(account('Candidat'), 'member')->post(route('social.request.store'), [
        'type' => 'application', 'offre' => $o->slug, 'message' => str_repeat('Motivation ', 4), 'contact_name' => 'C',
        'contact_email' => 'c@exemple.cm', 'cv' => UploadedFile::fake()->create('cv.docx', 10, 'application/msword'),
    ])->assertSessionHasErrors(['cv', 'consent', 'no_medical']);
    expect(SocialRequest::count())->toBe(0);
});

it('n’accepte pas de candidature sur une offre fermée, externe, ou vers sa propre page', function () {
    $owner = account();
    $page = memberPage($owner);
    $svc = app(ProjectService::class);
    $closed = $svc->saveOpportunity($page, opportunityData(['announce' => null]));
    $svc->setAvailability($closed, 'closed');
    $external = $svc->saveOpportunity($page, opportunityData(['apply_mode' => 'external', 'apply_url' => 'https://exemple.cm/postuler', 'announce' => null]));
    $base = ['type' => 'application', 'message' => str_repeat('Motivation ', 4), 'contact_name' => 'C', 'contact_email' => 'c@exemple.cm', 'consent' => '1', 'no_medical' => '1'];

    $this->actingAs(account('Candidat'), 'member')->post(route('social.request.store'), $base + ['offre' => $closed->slug])->assertSessionHasErrors('message');
    $this->post(route('social.request.store'), $base + ['offre' => $external->slug])->assertSessionHasErrors('message');
    $this->get(route('social.opportunity', $external))->assertSee('https://exemple.cm/postuler', false);
    $this->actingAs($owner, 'member')->get(route('social.request.create', ['type' => 'application', 'offre' => $external->slug]))->assertForbidden();
    expect(SocialRequest::count())->toBe(0);
});

it('propose « Je m’engage » seulement si le projet cherche des bénévoles, et « Soutenir » seulement s’il cherche des partenaires', function () {
    $owner = account();
    $page = memberPage($owner);
    $project = app(ProjectService::class)->saveProject($page, projectData(['announce' => null]), null, null, $owner);
    $member = account('Benevole');

    $this->actingAs($member, 'member')->get(route('social.project', $project))->assertSee('Je m’engage')->assertDontSee('Soutenir ou devenir partenaire');
    $this->get(route('social.request.create', ['type' => 'support', 'projet' => $project->slug]))->assertNotFound();

    $this->post(route('social.request.store'), ['type' => 'engagement', 'projet' => $project->slug, 'subtype' => 'benevolat',
        'availability' => 'Le samedi', 'message' => 'Je peux aider à l’accueil les samedis matin.', 'contact_name' => 'Bénévole',
        'contact_email' => 'b@exemple.cm', 'consent' => '1', 'no_medical' => '1'])->assertRedirect();
    $r = SocialRequest::firstOrFail();
    expect($r->kind)->toBe('engagement')->and($r->project_id)->toBe($project->id);

    // Soutien au niveau de l'organisation (sans projet) : possible, et sans aucun paiement.
    $this->get(route('social.request.create', ['type' => 'support', 'page' => $page->slug]))->assertOk()->assertSee('ne collecte ni ne transfère aucun fonds');
});

it('fait suivre la réponse de la page jusqu’au demandeur, avec transitions contrôlées', function () {
    $owner = account();
    $page = memberPage($owner);
    $project = app(ProjectService::class)->saveProject($page, projectData(['announce' => null, 'seeks_partners' => '1', 'partner_needs' => 'Matériel de dépistage']), null, null, $owner);
    $partner = account('Partenaire');
    $this->actingAs($partner, 'member')->post(route('social.request.store'), ['type' => 'support', 'projet' => $project->slug, 'subtype' => 'materiel',
        'organisation_name' => 'Pharmacie du Centre', 'message' => 'Nous pouvons offrir 200 bandelettes de glycémie.', 'contact_name' => 'Partenaire',
        'contact_phone' => '+237677000000', 'consent' => '1', 'no_medical' => '1'])->assertRedirect();
    $r = SocialRequest::firstOrFail();

    $this->actingAs($owner, 'member')->get(route('social.manage.request', [$page->slug, $r->reference]))->assertOk()->assertSee('Pharmacie du Centre');
    $this->post(route('social.manage.request.decide', [$page->slug, $r->reference]), ['status' => 'closed'])->assertStatus(422);
    $this->post(route('social.manage.request.decide', [$page->slug, $r->reference]), ['status' => 'accepted', 'note' => 'Merci ! Nous vous appelons demain.'])->assertSessionHas('ok');
    expect($r->fresh()->status)->toBe('accepted');
    expect(SocialNotification::where('public_account_id', $partner->id)->where('type', 'demande')->exists())->toBeTrue();

    $this->actingAs($partner, 'member')->get(route('social.request', $r->reference))->assertOk()->assertSee('Acceptée')->assertSee('Nous vous appelons demain');
    $this->actingAs(account('Autre'), 'member')->get(route('social.request', $r->reference))->assertNotFound();
});

it('permet au demandeur de retirer une demande ouverte', function () {
    $page = memberPage(account());
    $o = app(ProjectService::class)->saveOpportunity($page, opportunityData(['announce' => null]));
    $c = account('Candidat');
    $this->actingAs($c, 'member')->post(route('social.request.store'), ['type' => 'application', 'offre' => $o->slug, 'message' => str_repeat('Motivation ', 4),
        'contact_name' => 'C', 'contact_email' => 'c@exemple.cm', 'consent' => '1', 'no_medical' => '1']);
    $r = SocialRequest::firstOrFail();
    $this->post(route('social.request.withdraw', $r->reference))->assertRedirect();
    expect($r->fresh()->status)->toBe('withdrawn');
    $this->get(route('social.requests'))->assertOk()->assertSee('Retirée');
});

it('interdit toute demande vers une organisation de démonstration', function () {
    $page = memberPage(account(), ['is_demo' => true]);
    $this->actingAs(account('Visiteur'), 'member')->get(route('social.request.create', ['type' => 'engagement', 'page' => $page->slug]))->assertForbidden();
});

it('fonctionne aussi pour une mutuelle gérée, via ses gestionnaires désignés', function () {
    $mutual = mutualPage();
    $manager = account('Gestionnaire');
    PageAdmin::create(['mutual_id' => $mutual->id, 'public_account_id' => $manager->id, 'role' => 'admin']);

    $this->actingAs($manager, 'member')->post(route('social.manage.opportunity.store', $mutual->slug), opportunityData(['title' => 'Agent d’adhésion à Douala']))->assertRedirect();
    $o = Opportunity::firstOrFail();
    expect($o->mutual_id)->toBe($mutual->id);

    $this->actingAs(account('Candidat'), 'member')->post(route('social.request.store'), ['type' => 'application', 'offre' => $o->slug,
        'message' => str_repeat('Motivation ', 4), 'contact_name' => 'C', 'contact_email' => 'c@exemple.cm', 'consent' => '1', 'no_medical' => '1'])->assertRedirect();
    expect(SocialRequest::where('mutual_id', $mutual->id)->count())->toBe(1);
    $this->actingAs($manager, 'member')->get(route('social.manage.requests', $mutual->slug))->assertOk()->assertSee('C · Candidature');
});

it('supprime projets, offres, demandes et CV avec la page du membre', function () {
    $owner = account();
    $page = memberPage($owner);
    $o = app(ProjectService::class)->saveOpportunity($page, opportunityData(['announce' => null]));
    app(ProjectService::class)->saveProject($page, projectData(['announce' => null]), null);
    $this->actingAs(account('Candidat'), 'member')->post(route('social.request.store'), ['type' => 'application', 'offre' => $o->slug,
        'message' => str_repeat('Motivation ', 4), 'contact_name' => 'C', 'contact_email' => 'c@exemple.cm', 'cv' => pdf(), 'consent' => '1', 'no_medical' => '1']);
    $cv = SocialRequest::firstOrFail()->cv_path;

    $this->actingAs($owner, 'member')->post(route('social.pages.destroy', $page->slug), ['confirm_name' => $page->name])->assertRedirect();
    expect(Project::withTrashed()->count())->toBe(0)->and(Opportunity::withTrashed()->count())->toBe(0)->and(SocialRequest::count())->toBe(0);
    Storage::disk('social_private')->assertMissing($cv);
});

it('ouvre les pages publiques aux visiteurs et renvoie vers la connexion pour agir', function () {
    $page = memberPage(account());
    $o = app(ProjectService::class)->saveOpportunity($page, opportunityData(['announce' => null]));
    $this->get(route('social.opportunities'))->assertOk();
    $this->get(route('social.projects'))->assertOk();
    $this->get(route('social.opportunity', $o))->assertOk()->assertSee(route('social.login'));
    $this->get(route('social.request.create', ['type' => 'application', 'offre' => $o->slug]))->assertRedirect();
    $this->get(route('social.requests'))->assertRedirect();
});

it('sert les pièces d’adhésion en privé, jamais en cache partagé (correctif)', function () {
    Storage::disk('social_private')->put('adhesions/x/ADH-TEST/piece.pdf', "%PDF-1.4\n%%EOF");
    $doc = new \App\Models\Social\AdhesionDocument(['path' => 'adhesions/x/ADH-TEST/piece.pdf', 'mime' => 'application/pdf', 'original_name' => 'cni.pdf']);
    $cc = \App\Http\Controllers\Social\AdhesionController::download($doc)->headers->get('Cache-Control');
    expect($cc)->toContain('private')->toContain('no-store')->not->toContain('public');
});

it('accepte une candidature juste après la création de l’offre (valeurs par défaut sur l’objet)', function () {
    $page = memberPage(account());
    $o = app(ProjectService::class)->saveOpportunity($page, opportunityData(['announce' => null]));
    expect($o->isOpen())->toBeTrue();
    $r = app(\App\Services\Social\RequestService::class)->submit('application', $page, account('Rapide'),
        ['message' => str_repeat('Motivation ', 4), 'contact_name' => 'R', 'contact_email' => 'r@exemple.cm'], null, $o);
    expect($r->status)->toBe('submitted');
});

it('affiche l’écran réseau « Projets et offres » et permet de masquer une offre', function () {
    $page = memberPage(account());
    $p = app(ProjectService::class)->saveProject($page, projectData(['announce' => null]), null);
    app(ProjectService::class)->saveIndicator($p, ['label' => 'Villages couverts', 'value' => 7]);
    $o = app(ProjectService::class)->saveOpportunity($page, opportunityData(['announce' => null]));
    Role::findOrCreate('moderator', 'network');
    $mod = NetworkUser::factory()->create(['mfa_enabled' => true]);
    $mod->assignRole('moderator');
    $this->actingAs($mod, 'network')->withSession(['mfa_passed' => true]);

    foreach (['resultats' => 'Villages couverts', 'projets' => 'Dépistage du diabète', 'offres' => 'Infirmier pour la tournée'] as $tab => $see) {
        $this->get(route('network.social.projects', ['onglet' => $tab]))->assertOk()->assertSee($see);
    }
    $this->get(route('network.social.projects', ['onglet' => 'verifies']))->assertOk();
    $this->post(route('network.social.opportunities.toggle', $o->id))->assertSessionHas('success');
    expect($o->fresh()->status)->toBe('hidden');
    $this->get(route('social.opportunities'))->assertDontSee('Infirmier pour la tournée');
});
