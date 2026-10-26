<?php

use App\Enums\EngagementStatus;
use App\Enums\OrganizationManagerRole;
use App\Models\Central\EngagementRequest;
use App\Models\Central\SupportOffer;
use App\Notifications\Social\RequestReceived;
use App\Notifications\Social\RequestStatusChanged;

require_once __DIR__.'/SocialHelpers.php';

/** Lot 2 — Actions A ("Je m'engage") and C ("Soutenir") end to end: storage, dedupe, rights, notifications. */

beforeEach(function () {
    $this->owner = socialAccount('Owner');
    $this->org = socialOrg($this->owner);
    $this->event = orgPost($this->org, $this->owner, [
        'type' => 'event', 'body' => 'Forum sur la protection sociale',
        'event_starts_at' => now()->addMonth()->format('Y-m-d H:i'),
    ]);
    $this->volunteer = socialAccount('Volunteer');
});

it('records a "Je m\'engage" request and notifies the organization admins', function () {
    $this->actingAs($this->volunteer, 'member')->post(route('organizations.engage', $this->org), [
        'contribution_type' => 'benevolat', 'message' => 'Disponible le samedi', 'post_id' => $this->event->id,
    ])->assertRedirect(route('my.requests'));

    $request = EngagementRequest::firstOrFail();
    expect($request->status)->toBe(EngagementStatus::Envoyee)
        ->and($request->post_id)->toBe($this->event->id)
        ->and($this->owner->notifications()->where('type', RequestReceived::class)->count())->toBe(1);

    $this->actingAs($this->volunteer, 'member')->get(route('my.requests'))->assertOk()->assertSee('Disponible le samedi');
});

it('blocks duplicate open requests on the same subject', function () {
    $payload = ['contribution_type' => 'benevolat', 'post_id' => $this->event->id];
    $this->actingAs($this->volunteer, 'member')->post(route('organizations.engage', $this->org), $payload);
    $this->actingAs($this->volunteer, 'member')->post(route('organizations.engage', $this->org), $payload)
        ->assertSessionHasErrors('organization');

    expect(EngagementRequest::count())->toBe(1);
});

it('refuses requests from the organization\'s own managers', function () {
    $this->actingAs($this->owner, 'member')->post(route('organizations.engage', $this->org), ['contribution_type' => 'benevolat'])
        ->assertSessionHasErrors('organization');

    expect(EngagementRequest::count())->toBe(0);
});

it('refuses the action on a post where it is not offered', function () {
    $plain = orgPost($this->org, $this->owner, ['body' => 'Simple message']);

    $this->actingAs($this->volunteer, 'member')->post(route('organizations.engage', $this->org), [
        'contribution_type' => 'benevolat', 'post_id' => $plain->id,
    ])->assertSessionHasErrors('post_id');
});

it('lets only owners/admins answer, enforces the lifecycle and notifies the requester', function () {
    $this->actingAs($this->volunteer, 'member')->post(route('organizations.engage', $this->org), [
        'contribution_type' => 'competence', 'post_id' => $this->event->id,
    ]);
    $request = EngagementRequest::firstOrFail();
    $editor = socialAccount('Editor');
    addManager($this->org, $editor, OrganizationManagerRole::Editor);
    $url = route('my.organizations.respond', [$this->org, 'engagement', $request->id]);

    $this->actingAs($editor, 'member')->post($url, ['status' => 'acceptee'])->assertForbidden();
    $this->actingAs($this->owner, 'member')->post($url, ['status' => 'cloturee'])->assertSessionHasErrors('status');

    // Contact details stay hidden until the request is accepted.
    $this->actingAs($this->owner, 'member')->get(route('my.organizations.inbox', $this->org))
        ->assertOk()->assertDontSee($this->volunteer->phone);

    $this->actingAs($this->owner, 'member')->post($url, ['status' => 'acceptee', 'note' => 'Bienvenue !'])->assertRedirect();
    expect($request->refresh()->status)->toBe(EngagementStatus::Acceptee)
        ->and($request->responded_by_account_id)->toBe($this->owner->id)
        ->and($this->volunteer->notifications()->where('type', RequestStatusChanged::class)->count())->toBe(1);

    $this->actingAs($this->owner, 'member')->get(route('my.organizations.inbox', $this->org))->assertSee($this->volunteer->phone);
    $this->actingAs($this->volunteer, 'member')->get(route('my.requests'))->assertSee('Bienvenue !');
});

it('does not let a manager of another organization answer', function () {
    $this->actingAs($this->volunteer, 'member')->post(route('organizations.engage', $this->org), ['contribution_type' => 'benevolat']);
    $request = EngagementRequest::firstOrFail();
    $otherOwner = socialAccount('OtherOwner');
    $otherOrg = socialOrg($otherOwner, ['name' => 'Autre organisation']);

    $this->actingAs($otherOwner, 'member')
        ->post(route('my.organizations.respond', [$otherOrg, 'engagement', $request->id]), ['status' => 'acceptee'])
        ->assertNotFound();
    expect($request->refresh()->status)->toBe(EngagementStatus::Envoyee);
});

it('lets the requester withdraw an open request, and nobody else', function () {
    $this->actingAs($this->volunteer, 'member')->post(route('organizations.engage', $this->org), ['contribution_type' => 'benevolat']);
    $request = EngagementRequest::firstOrFail();
    $url = route('my.requests.withdraw', ['engagement', $request->id]);

    $this->actingAs(socialAccount('Other'), 'member')->post($url)->assertForbidden();
    $this->actingAs($this->volunteer, 'member')->post($url)->assertRedirect();
    expect($request->refresh()->status)->toBe(EngagementStatus::Cloturee);
});

it('records a support proposal as an intent only, with no payment involved', function () {
    $opportunity = orgPost($this->org, $this->owner, [
        'type' => 'opportunity', 'opportunity_kind' => 'besoin_equipement', 'body' => 'Équipement d’un centre de santé rural',
    ]);

    $this->actingAs($this->volunteer, 'member')->post(route('organizations.support', $this->org), [
        'support_type' => 'materiel', 'message' => 'Nous pouvons fournir deux lits.',
        'contact_organization' => 'Fondation X', 'contact_email' => 'contact@fondation.test', 'post_id' => $opportunity->id,
        'amount' => 100000,
    ])->assertRedirect(route('my.requests'));

    $offer = SupportOffer::firstOrFail();
    expect($offer->contact_info)->toBe(['organization' => 'Fondation X', 'email' => 'contact@fondation.test'])
        ->and(array_keys($offer->getAttributes()))->not->toContain('amount')
        ->and($this->owner->notifications()->count())->toBe(1);

    $this->actingAs($this->owner, 'member')->get(route('my.organizations.inbox', [$this->org, 'tab' => 'support']))
        ->assertOk()->assertSee('Fondation X');
});
