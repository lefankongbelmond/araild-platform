<?php

/*
 * Lot 4 — fonctionnalités et design du Lot 2 portés en production : domaines d'intervention,
 * « Découvrir », accueil /communaute, en-tête de profil, tuiles d'action, page projet,
 * tendances de la semaine.
 */

use App\Models\Central\Country;
use App\Models\Social\Post;
use App\Models\Social\Project;
use App\Services\Social\PostService;
use App\Services\Social\ProjectService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('social');
    Storage::fake('social_private');
    $this->seed(\Database\Seeders\ConfigReferenceSeeder::class);
    $this->country = Country::where('active', true)->firstOrFail();
});

it('enregistre les domaines d’une page de membre et la retrouve par domaine dans « Découvrir »', function () {
    $owner = account();
    $form = ['name' => 'Jeunes pour l’Environnement', 'category' => 'osc', 'country_id' => $this->country->id, 'region' => 'Ouest', 'accept' => '1'];
    $this->actingAs($owner, 'member')->post(route('social.pages.store'), $form + ['domains' => ['environnement', 'inconnu']])
        ->assertSessionHasErrors('domains.1');                                    // code inconnu refusé
    $this->post(route('social.pages.store'), $form + ['domains' => ['environnement', 'agriculture']])->assertRedirect();
    $page = \App\Models\Central\Mutual::where('name', 'Jeunes pour l’Environnement')->firstOrFail();
    expect($page->profile->domains)->toBe(['agriculture', 'environnement']);     // rangés dans l'ordre de la liste

    memberPage(account('Autre'), ['domains' => ['sante']]);
    $this->get(route('social.discover', ['domaine' => 'environnement']))->assertOk()->assertSee('Jeunes pour l’Environnement')->assertDontSee('Association ');
    $this->get(route('social.discover', ['domaine' => 'sante']))->assertDontSee('Jeunes pour l’Environnement');
    $this->get(route('social.discover', ['region' => 'Ouest']))->assertSee('Jeunes pour l’Environnement');
    $this->get(route('social.discover', ['q' => 'Environnement']))->assertSee('Jeunes pour l’Environnement');
});

it('cherche aussi projets, événements et opportunités dans « Découvrir », un projet héritant des domaines de sa page', function () {
    $page = memberPage(account(), ['domains' => ['sante']]);
    $svc = app(ProjectService::class);
    $svc->saveProject($page, projectData(['announce' => null]), null);                                             // sans domaine → hérite « santé »
    $svc->saveProject($page, projectData(['title' => 'Jardins scolaires', 'domains' => ['education'], 'announce' => null]), null);
    $svc->saveOpportunity($page, opportunityData(['announce' => null]));
    app(PostService::class)->create(['body' => 'Journée de dépistage gratuite', 'type' => 'evenement',
        'event_starts_at' => now()->addWeek()->format('Y-m-d H:i:s'), 'event_location' => 'Yaoundé'], [], null, $page);

    $this->get(route('social.discover', ['onglet' => 'projets', 'domaine' => 'sante']))->assertOk()->assertSee('Dépistage du diabète')->assertDontSee('Jardins scolaires');
    $this->get(route('social.discover', ['onglet' => 'projets', 'domaine' => 'education']))->assertSee('Jardins scolaires')->assertDontSee('Dépistage du diabète');
    $this->get(route('social.discover', ['onglet' => 'opportunites']))->assertOk()->assertSee('Infirmier pour la tournée');
    $this->get(route('social.discover', ['onglet' => 'evenements']))->assertOk()->assertSee('Journée de dépistage gratuite');
});

it('affiche l’accueil Lot 2 avec des chiffres réels et sans compter les démonstrations', function () {
    $page = memberPage(account(), ['domains' => ['sante']]);
    memberPage(account('Demo'), ['is_demo' => true, 'domains' => ['sante']]);
    app(ProjectService::class)->saveProject($page, projectData(['announce' => null, 'progress' => 60]), null);

    $html = $this->get(route('social.landing'))->assertOk()
        ->assertSee('Les initiatives qui changent la vie')->assertSee('Explorer par domaine')->assertSee('Dépistage du diabète')->assertSee('60 %')
        ->getContent();
    expect(\App\Http\Controllers\Social\DiscoverController::stats()['organisations'])->toBe(1);   // la page « Démo » est exclue
    expect($html)->toContain('<b>1</b><span>Organisations</span>');
});

it('enregistre l’avancement et les bénéficiaires d’un projet et les affiche sur la page projet', function () {
    $owner = account();
    $page = memberPage($owner);
    $this->actingAs($owner, 'member')->post(route('social.manage.project.store', $page->slug), projectData([
        'progress' => '60', 'beneficiaries' => '12 villages, environ 3 000 habitants', 'domains' => ['sante'],
        'partners' => "CRADAR\nGreen Future Partnership", 'seeks_partners' => '1', 'partner_needs' => 'Matériel',
    ]))->assertRedirect();
    $p = Project::firstOrFail();
    expect($p->progress)->toBe(60)->and($p->domains)->toBe(['sante']);

    $this->actingAs(account('Visiteur'), 'member')->get(route('social.project', $p))->assertOk()
        ->assertSee('60 %')->assertSee('12 villages, environ 3 000 habitants')->assertSee('Green Future Partnership')
        ->assertSee('Je m’engage')->assertSee('Soutenir')->assertSee('Publications liées');
    $this->actingAs($owner, 'member')->post(route('social.manage.project.update', [$page->slug, $p->id]), projectData(['progress' => '120']))
        ->assertSessionHasErrors('progress');
});

it('montre les tuiles d’action Lot 2 sous une publication liée à un projet, sauf pour ses gestionnaires', function () {
    $owner = account();
    $page = memberPage($owner);
    $this->actingAs($owner, 'member')->post(route('social.manage.project.store', $page->slug),
        projectData(['seeks_partners' => '1', 'partner_needs' => 'Matériel']));
    $post = Post::whereNotNull('project_id')->firstOrFail();

    $this->actingAs(account('Lecteur'), 'member')->get(route('social.post', $post))->assertOk()
        ->assertSee('mx-tile--engage', false)->assertSee('mx-tile--project', false)->assertSee('mx-tile--support', false);
    $this->actingAs($owner, 'member')->get(route('social.post', $post))
        ->assertDontSee('mx-tile--engage', false)->assertSee('mx-tile--project', false);
});

it('affiche les boutons Suivre, Je m’engage et Soutenir sur le profil, mais pas pour une démonstration', function () {
    $page = memberPage(account(), ['domains' => ['solidarite']]);
    $this->actingAs(account('Visiteur'), 'member')->get(route('social.mutual', $page->slug))->assertOk()
        ->assertSee('mx-btn--engage', false)->assertSee('mx-btn--support', false)->assertSee('Solidarité')->assertSee('Projets');
    $demo = memberPage(account('D'), ['is_demo' => true]);
    $this->get(route('social.mutual', $demo->slug))->assertOk()->assertDontSee('mx-btn--engage', false);
});

it('calcule les tendances de la semaine à partir des vrais mots-clés publiés', function () {
    $page = memberPage(account());
    $ps = app(PostService::class);
    $ps->create(['body' => 'Campagne #SantéCommunautaire et #Jeunesse'], [], null, $page);
    $ps->create(['body' => 'Encore #santécommunautaire !'], [], null, $page);
    $old = $ps->create(['body' => 'Vieux #Archive'], [], null, $page);
    Post::whereKey($old->id)->update(['published_at' => now()->subDays(10)]);

    $trends = app(\App\Services\Social\FeedService::class)->trends(5);
    expect($trends)->toBe(['santécommunautaire' => 2, 'jeunesse' => 1]);
    $this->get(route('social.all'))->assertOk()->assertSee('Tendances de la semaine')->assertSee('#santécommunautaire');
});

it('donne les domaines Santé et Protection sociale aux mutuelles existantes lors de la migration', function () {
    $mutual = mutualPage();
    \Illuminate\Support\Facades\DB::connection('central')->table('mutual_profiles')->where('mutual_id', $mutual->id)->update(['domains' => null]);
    $migration = require database_path('migrations/2026_10_28_000100_social_domains_and_project_progress.php');
    $migration->down();
    $migration->up();
    expect($mutual->profile()->first()->domains)->toBe(['sante', 'protection_sociale']);
});
