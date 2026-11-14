<?php

/*
 * Mission « Mutuelle paramétrable », L6 (2026-11-22) : profil 4 de la Partie 7, « gestionnaire des
 * cotisations » (rôle contribution_manager). Il encaisse au guichet et tient les relevés ; il n'a ni
 * trésorerie, ni comptabilité, ni donnée de santé ; il ne valide pas ses propres annulations.
 * VRAIE base de mutuelle (ou tables préfixées).
 */

use App\Models\Tenant\ContributionPayment;
use App\Models\Tenant\Guarantee;
use App\Models\Tenant\GuaranteeVersion;
use App\Models\Tenant\Member;
use App\Models\Tenant\Subscription;
use App\Models\Tenant\User;
use App\Services\ScheduleGenerationService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-27 10:00:00'));
    provisionTestMutual();
    $this->member = $this->mutual->run(function () {
        $g = Guarantee::create(['code' => 'ESS', 'name' => 'Essentielle', 'status' => 'active']);
        $v = GuaranteeVersion::create(['guarantee_id' => $g->id, 'version_no' => 1, 'valid_from' => '2026-01-01', 'base_contribution_minor' => 5000,
            'periodicity' => 'monthly', 'coverage_rate' => 80, 'copay_rate' => 20, 'observation_days' => 0]);
        $m = Member::create(['member_code' => 'MUT-002200', 'first_name' => 'Ada', 'last_name' => 'Mballa', 'birth_date' => '1992-02-02',
            'sex' => 'F', 'status' => 'active', 'joined_at' => '2026-09-01']);
        $s = Subscription::create(['member_id' => $m->id, 'guarantee_version_id' => $v->id, 'contribution_minor' => 5000, 'effective_date' => '2026-09-01',
            'observation_ends_on' => '2026-09-01', 'status' => 'validated', 'captured_by' => 1, 'validated_at' => now()]);
        app(ScheduleGenerationService::class)->generate(Subscription::with('guaranteeVersion')->find($s->id));

        return $m;
    });
    staffUser('caisse@equipe.test', ['contribution_manager']);
    staffUser('ctrl@equipe.test', ['controller_validator']);
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('encaisse au guichet et tient les relevés, sans trésorerie, comptabilité ni données de santé', function () {
    $h = $this->host;
    $id = $this->member->id;
    $as = fn () => as_('caisse@equipe.test');

    foreach (['/cotisations', "/cotisations/adherent/{$id}", '/adherents', '/souscriptions'] as $url) {
        expect($as()->get($h . $url)->status())->toBe(200, $url);
    }
    foreach (['/tresorerie', '/comptabilite/journal', '/recours', '/pilotage', '/rapports', '/equipe', '/formules', '/accords-prealables'] as $url) {
        expect($as()->get($h . $url)->status())->toBe(403, $url);
    }
    // Le menu ne montre que ses rubriques.
    $as()->get($h . '/cotisations')->assertSee('Gestionnaire des cotisations')->assertDontSee('/tresorerie"', false)->assertDontSee('/recours"', false);

    // Encaissement réel : paiement, reçu, mouvement de trésorerie créé par le service (pas par le rôle).
    $as()->post($h . "/cotisations/adherent/{$id}/encaissement", ['amount' => '5000', 'paid_on' => '2026-10-20', 'mode' => 'cash'])->assertRedirect();
    $pay = $this->mutual->run(fn () => ContributionPayment::latest('id')->first());
    expect($pay)->not->toBeNull()->and($pay->amount_minor)->toBe(5000);

    // Annulation : il la demande ; il ne peut pas la décider (4 yeux).
    $as()->post($h . "/cotisations/encaissements/{$pay->collection_ref}/annulation", ['reason' => 'Erreur de saisie au guichet'])->assertRedirect();
    $cancel = $this->mutual->run(fn () => \App\Models\Tenant\CollectionCancellation::latest('id')->first());
    expect($cancel)->not->toBeNull();
    $as()->post($h . "/cotisations/annulations/{$cancel->id}/validation")->assertForbidden();
});

it('ne se cumule pas avec le rôle de contrôleur et exige la double authentification', function () {
    asAdmin()->post($this->host . '/equipe', ['first_name' => 'Cumul', 'last_name' => 'Interdit', 'email' => 'cumul@equipe.test',
        'roles' => ['contribution_manager', 'controller_validator']])->assertSessionHasErrors('roles');
    expect($this->mutual->run(fn () => User::where('email', 'cumul@equipe.test')->exists()))->toBeFalse();
    expect(config('mxconnect.mfa_required_roles'))->toContain('contribution_manager');
    // Le rôle existe dans la mutuelle (créé par le paramétrage initial, ou par la migration pour les mutuelles existantes).
    expect($this->mutual->run(fn () => \Spatie\Permission\Models\Role::where('name', 'contribution_manager')->where('guard_name', 'web')->exists()))->toBeTrue();
    asAdmin()->get($this->host . '/equipe/roles-et-droits')->assertOk()->assertSee('Gestionnaire des cotisations');
});
