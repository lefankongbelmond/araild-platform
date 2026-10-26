<?php

use App\Enums\OrganizationManagerRole;
use App\Models\Central\Project;

require_once __DIR__.'/SocialHelpers.php';

/** Lot 2 — Action B (projects & results) and the public pages rendering real data. */

it('lets an owner create a project and add results that always stay "declared"', function () {
    $owner = socialAccount();
    $org = socialOrg($owner);

    $this->actingAs($owner, 'member')->post(route('projects.store', $org), [
        'title' => 'Plantation de 500 arbres', 'status' => 'en_cours', 'progress_percent' => 60,
        'zone' => 'Région de l’Ouest', 'partners' => "CRADAR\nGreen Future Partnership",
    ])->assertRedirect();
    $project = Project::firstOrFail();
    expect($project->partners)->toBe(['CRADAR', 'Green Future Partnership']);

    $this->actingAs($owner, 'member')->post(route('projects.indicators.store', $project), [
        'label' => 'Arbres plantés', 'value' => '500', 'is_verified' => 1, 'verified_by' => 1,
    ])->assertRedirect();

    $indicator = $project->indicators()->firstOrFail();
    expect($indicator->is_verified)->toBeFalse()->and($indicator->verified_by)->toBeNull();

    $this->get(route('projects.show', $project))->assertOk()
        ->assertSee('Plantation de 500 arbres')
        ->assertSee(__('mxconnect.social.project.declared'))
        ->assertDontSee(__('mxconnect.social.project.verified').'</span>', false);
});

it('refuses project management to editors and strangers', function () {
    $owner = socialAccount();
    $org = socialOrg($owner);
    $editor = socialAccount('Editor');
    addManager($org, $editor, OrganizationManagerRole::Editor);

    $payload = ['title' => 'Projet pirate', 'status' => 'planifie', 'progress_percent' => 0];
    $this->actingAs($editor, 'member')->post(route('projects.store', $org), $payload)->assertForbidden();
    $this->actingAs(socialAccount('Stranger'), 'member')->post(route('projects.store', $org), $payload)->assertForbidden();
    expect(Project::count())->toBe(0);
});

it('renders the public pages for a guest with live data and empty states', function () {
    $this->get(route('home'))->assertOk()->assertSee(__('mxconnect.social.home.empty_projects'));
    $this->get(route('feed'))->assertOk();
    $this->get(route('discover'))->assertOk();

    $owner = socialAccount();
    $org = socialOrg($owner, ['name' => 'Centre Médical La Vie', 'type' => 'autre', 'domains' => ['sante']]);
    orgPost($org, $owner, ['body' => 'Journée portes ouvertes #Santé']);

    $this->get(route('home'))->assertOk()->assertSee('Centre Médical La Vie');
    $this->get(route('organizations.show', $org))->assertOk()->assertSee('Journée portes ouvertes');
    $this->get(route('organizations.show', [$org, 'onglet' => 'a-propos']))->assertOk();
    $this->get(route('feed', ['onglet' => 'organisations']))->assertOk()->assertSee('Journée portes ouvertes');
});

it('filters discovery results by keyword, domain and type', function () {
    $owner = socialAccount();
    socialOrg($owner, ['name' => 'Mutuelle Espoir Santé', 'type' => 'mutual', 'domains' => ['sante']]);
    socialOrg($owner, ['name' => 'Initiatives Vertes Afrique', 'type' => 'ong', 'domains' => ['environnement']]);

    $this->get(route('discover', ['domaine' => 'environnement']))->assertOk()
        ->assertSee('Initiatives Vertes Afrique')->assertDontSee('Mutuelle Espoir Santé');
    $this->get(route('discover', ['q' => 'Espoir']))->assertOk()
        ->assertSee('Mutuelle Espoir Santé')->assertDontSee('Initiatives Vertes Afrique');
    $this->get(route('discover', ['type' => 'ong']))->assertOk()
        ->assertSee('Initiatives Vertes Afrique')->assertDontSee('Mutuelle Espoir Santé');
    $this->get(route('discover', ['domaine' => 'not-a-domain']))->assertRedirect();
});

it('renders the signed-in spaces', function () {
    $owner = socialAccount();
    $org = socialOrg($owner);
    $project = app(\App\Services\Social\ProjectService::class)->create($org, $owner, ['title' => 'Projet A', 'status' => 'planifie', 'progress_percent' => 0]);

    $this->actingAs($owner, 'member');
    $this->get(route('feed'))->assertOk()->assertSee($org->name);
    $this->get(route('my.organizations'))->assertOk()->assertSee($org->name);
    $this->get(route('my.organizations.inbox', $org))->assertOk();
    $this->get(route('my.requests'))->assertOk();
    $this->get(route('my.notifications'))->assertOk();
    $this->get(route('organizations.create'))->assertOk();
    $this->get(route('organizations.edit', $org))->assertOk();
    $this->get(route('projects.create', $org))->assertOk();
    $this->get(route('projects.edit', $project))->assertOk();
    $this->get(route('projects.show', $project))->assertOk();

    $visitor = socialAccount('Visitor');
    $this->actingAs($visitor, 'member')->get(route('organizations.engage.form', $org))->assertOk();
    $this->actingAs($visitor, 'member')->get(route('organizations.support.form', [$org, 'projet' => $project->id]))->assertOk()->assertSee('Projet A');
});
