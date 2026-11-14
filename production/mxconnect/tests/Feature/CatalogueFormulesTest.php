<?php

/*
 * Mission « Mutuelle paramétrable », L1–L2 (2026-11-18) : écrans du catalogue des formules,
 * dérogations « 4 yeux », MUTUELLE TEST, refus de souscription selon la fiche de la formule.
 * VRAIE base de mutuelle (ou tables préfixées avec MX_TEST_STORAGE=prefix).
 */

use App\Models\Tenant\ActType;
use App\Models\Tenant\CoverageException;
use App\Models\Tenant\CoverageLimit;
use App\Models\Tenant\CoverageRule;
use App\Models\Tenant\Guarantee;
use App\Models\Tenant\GuaranteeVersion;
use App\Models\Tenant\Member;
use App\Models\Tenant\ServiceCategory;
use App\Models\Tenant\Subscription;
use App\Models\Tenant\WaitingPeriod;

beforeEach(function () {
    provisionTestMutual();
    staffUser('presta@equipe.test', ['benefits_manager']);
    staffUser('controle@equipe.test', ['controller_validator']);
    staffUser('tresor@equipe.test', ['treasurer_accountant']);
    staffUser('auditeur@equipe.test', ['auditor']);
    [$this->g, $this->pharma, $this->cat] = $this->mutual->run(function () {
        $g = Guarantee::create(['code' => 'FAM', 'name' => 'Famille', 'status' => 'active']);
        GuaranteeVersion::create(['guarantee_id' => $g->id, 'version_no' => 1, 'valid_from' => now()->subMonth()->toDateString(),
            'base_contribution_minor' => 5000, 'periodicity' => 'monthly', 'coverage_rate' => 70, 'copay_rate' => 30, 'observation_days' => 30]);
        $cat = ServiceCategory::where('code', 'pharmacy')->value('id');
        $pharma = ActType::create(['code' => 'PHA-MED', 'label' => 'Médicaments sur ordonnance', 'category' => 'Pharmacie']);

        return [$g, $pharma, $cat];
    });
    tenancy()->end();
    $this->base = $this->host . '/formules';
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('paramètre une formule par l’interface : garantie, plafond partagé, carence, puis simulation', function () {
    asAdmin()->get($this->base)->assertOk()->assertSee('Catalogue des formules')->assertSee('Famille')->assertSee('Taux unique');

    // Rattachement proposé (code PHA-) mais pas enregistré tant que l'administrateur ne valide pas.
    asAdmin()->get($this->base . '/categories')->assertOk()->assertSee('proposé');
    expect($this->mutual->run(fn () => ActType::find($this->pharma->id)->service_category_id))->toBeNull();
    asAdmin()->post($this->base . '/categories/actes', ['map' => [$this->pharma->id => $this->cat]])->assertRedirect();
    expect($this->mutual->run(fn () => ActType::find($this->pharma->id)->service_category_id))->toBe($this->cat);

    // Un taux est obligatoire : rien n'est appliqué par défaut.
    asAdmin()->post("{$this->base}/{$this->g->id}/garanties", ['label' => 'Pharmacie', 'service_category_id' => $this->cat, 'beneficiary_category' => 'all'])
        ->assertSessionHasErrors('coverage_rate');
    asAdmin()->post("{$this->base}/{$this->g->id}/garanties", ['label' => 'Pharmacie', 'service_category_id' => $this->cat, 'beneficiary_category' => 'all',
        'coverage_rate' => '70', 'copay_rate' => '30', 'emergency_exempt' => 1, 'active' => 1])->assertRedirect()->assertSessionHasNoErrors();
    // Doublon catégorie × bénéficiaire refusé.
    asAdmin()->post("{$this->base}/{$this->g->id}/garanties", ['label' => 'Bis', 'service_category_id' => $this->cat, 'beneficiary_category' => 'all', 'coverage_rate' => '50'])
        ->assertSessionHasErrors('rule');
    $key = $this->mutual->run(fn () => CoverageRule::value('rule_key'));

    // Plafond : montant et/ou nombre exigé ; « par acte » seulement en montant.
    asAdmin()->post("{$this->base}/{$this->g->id}/plafonds", ['scope' => 'year', 'holder' => 'family'])->assertSessionHasErrors('limit');
    asAdmin()->post("{$this->base}/{$this->g->id}/plafonds", ['scope' => 'act', 'holder' => 'family', 'max_count' => 2])->assertSessionHasErrors('limit');
    asAdmin()->post("{$this->base}/{$this->g->id}/plafonds", ['label' => 'Pharmacie annuelle', 'scope' => 'year', 'holder' => 'beneficiary',
        'amount' => '30000', 'rule_keys' => [$key], 'active' => 1])->assertSessionHasNoErrors();
    asAdmin()->post("{$this->base}/{$this->g->id}/carences", ['rule_key' => $key, 'beneficiary_category' => 'adult', 'days' => 30])->assertSessionHasNoErrors();

    $this->mutual->run(function () use ($key) {
        expect(CoverageLimit::first()->only(['rule_keys', 'amount_minor', 'scope']))->toBe(['rule_keys' => [$key], 'amount_minor' => 30000, 'scope' => 'year'])
            ->and(WaitingPeriod::first()->days)->toBe(30);
    });

    asAdmin()->get("{$this->base}/{$this->g->id}")->assertOk()->assertSee('Pharmacie annuelle')->assertSee('70 %')->assertSee('Adulte (18 ans et plus)');

    // Simulateur : adulte couvert depuis 10 jours → carence ; depuis 60 jours → 70 % avec le reste du plafond.
    $v = $this->mutual->run(fn () => GuaranteeVersion::value('id'));
    asAdmin()->post("{$this->base}/{$this->g->id}/simuler", ['version_id' => $v, 'act_type_id' => $this->pharma->id, 'amount' => '10000', 'age' => 35, 'days' => 10])
        ->assertRedirect();
    asAdmin()->get("{$this->base}/{$this->g->id}")->assertSee('DÉLAI DE CARENCE EN COURS');
    asAdmin()->post("{$this->base}/{$this->g->id}/simuler", ['version_id' => $v, 'act_type_id' => $this->pharma->id, 'amount' => '10000', 'age' => 35, 'days' => 60, 'used' => '25000']);
    asAdmin()->get("{$this->base}/{$this->g->id}")->assertSee('DROITS ACTIFS')->assertSee('5 000', false);   // 7 000 au taux, reste 5 000
});

it('versionne par l’interface une formule déjà utilisée (date d’effet exigée, aucun effet rétroactif)', function () {
    $this->mutual->run(function () {
        $v = GuaranteeVersion::first();
        CoverageRule::create(['guarantee_version_id' => $v->id, 'service_category_id' => $this->cat, 'label' => 'Pharmacie', 'beneficiary_category' => 'all', 'coverage_rate' => 70]);
        $v->update(['locked' => true]);
    });
    tenancy()->end();
    $rule = $this->mutual->run(fn () => CoverageRule::first());
    asAdmin()->post("{$this->base}/{$this->g->id}/garanties/{$rule->id}", ['label' => 'Pharmacie', 'service_category_id' => $this->cat,
        'beneficiary_category' => 'all', 'coverage_rate' => '60', 'active' => 1])->assertSessionHasErrors('effective_from');
    asAdmin()->post("{$this->base}/{$this->g->id}/garanties/{$rule->id}", ['label' => 'Pharmacie', 'service_category_id' => $this->cat,
        'beneficiary_category' => 'all', 'coverage_rate' => '60', 'active' => 1, 'effective_from' => now()->addDays(3)->toDateString(), 'change_note' => 'Baisse pharmacie'])
        ->assertSessionHasNoErrors()->assertSessionHas('success', fn ($m) => str_contains($m, 'V2'));
    $this->mutual->run(function () use ($rule) {
        expect(CoverageRule::find($rule->id)->coverage_rate)->toBe(70.0);                     // V1 intacte
        $v2 = GuaranteeVersion::where('version_no', 2)->first();
        expect($v2->change_note)->toBe('Baisse pharmacie')->and($v2->valid_from->toDateString())->toBe(now()->addDays(3)->toDateString())
            ->and($v2->rules()->first()->coverage_rate)->toBe(60.0)->and($v2->rules()->first()->rule_key)->toBe($rule->rule_key);
        expect(GuaranteeVersion::where('version_no', 1)->first()->valid_to->toDateString())->toBe(now()->addDays(2)->toDateString());
    });
});

it('applique la matrice des droits du catalogue et des dérogations', function () {
    as_('presta@equipe.test')->get($this->base)->assertOk();
    as_('presta@equipe.test')->post("{$this->base}/{$this->g->id}/garanties", ['label' => 'X', 'beneficiary_category' => 'all', 'coverage_rate' => 50])->assertForbidden();
    as_('tresor@equipe.test')->get("{$this->base}/{$this->g->id}")->assertOk()->assertDontSee('+ Ajouter une garantie');
    as_('auditeur@equipe.test')->get($this->base)->assertForbidden();
    as_('tresor@equipe.test')->get($this->host . '/derogations')->assertForbidden();
    as_('presta@equipe.test')->get($this->host . '/derogations')->assertOk();
    as_('presta@equipe.test')->post($this->host . '/formules/exemples')->assertForbidden();
    as_('controle@equipe.test')->post($this->host . '/derogations', [])->assertForbidden();
});

it('dérogation : demande justifiée, jamais décidée par le demandeur, retrait tracé', function () {
    $m = $this->mutual->run(fn () => Member::create(['member_code' => 'MUT-000900', 'first_name' => 'Awa', 'last_name' => 'Ngono',
        'birth_date' => '1990-01-01', 'sex' => 'F', 'status' => 'active', 'joined_at' => now()->toDateString()]));
    $url = $this->host . '/derogations';
    as_('presta@equipe.test')->post($url, ['member_code' => 'MUT-000900', 'kind' => 'limit_extra', 'service_category_id' => $this->cat,
        'valid_from' => now()->toDateString(), 'reason' => 'Traitement long', 'justification' => 'Avis du médecin-conseil joint au dossier.'])
        ->assertSessionHasErrors('amount');
    as_('presta@equipe.test')->post($url, ['member_code' => 'MUT-000900', 'kind' => 'limit_extra', 'service_category_id' => $this->cat, 'amount' => '5000',
        'valid_from' => now()->toDateString(), 'reason' => 'Traitement long', 'justification' => 'Avis du médecin-conseil joint au dossier.'])
        ->assertSessionHasNoErrors();
    $x = $this->mutual->run(fn () => CoverageException::first());
    expect($x->status)->toBe('requested')->and($x->amount_minor)->toBe(5000);

    // L'administrateur demande puis tente d'accorder sa propre demande : refusé.
    asAdmin()->post($url, ['member_code' => 'MUT-000900', 'kind' => 'waiting_waiver', 'valid_from' => now()->toDateString(),
        'reason' => 'Transfert', 'justification' => 'Attestation de couverture continue.'])->assertSessionHasNoErrors();
    $own = $this->mutual->run(fn () => CoverageException::where('kind', 'waiting_waiver')->first());
    asAdmin()->post("{$url}/{$own->id}/accorder")->assertSessionHas('error');

    as_('controle@equipe.test')->post("{$url}/{$x->id}/refuser", [])->assertSessionHasErrors('note');
    as_('controle@equipe.test')->post("{$url}/{$x->id}/accorder", ['note' => 'Conforme'])->assertSessionHas('success');
    as_('controle@equipe.test')->post("{$url}/{$x->id}/retirer", ['note' => 'Fin du traitement'])->assertSessionHas('success');
    $this->mutual->run(function () use ($x) {
        $y = CoverageException::find($x->id);
        expect($y->status)->toBe('revoked')->and($y->decision_note)->toContain('Retrait : Fin du traitement');
    });
    as_('controle@equipe.test')->get($url . '?adherent=MUT-000900')->assertOk()->assertSee('Retirée')->assertSee('Avis du médecin-conseil');
});

it('MUTUELLE TEST : marquage par le super-admin, formules d’exemple seulement en test, fiche respectée à la souscription', function () {
    // Pas encore marquée : chargement refusé.
    asAdmin()->post($this->host . '/formules/exemples')->assertForbidden();

    if (tenancy()->initialized) {
        tenancy()->end();
    }
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    \Spatie\Permission\Models\Role::findOrCreate('super_admin', 'network');
    $admin = \App\Models\Central\NetworkUser::factory()->create(['mfa_enabled' => true]);
    $admin->assignRole('super_admin');
    $c = rtrim((string) config('app.url'), '/');
    $this->actingAs($admin, 'network')->withSession(['mfa_passed' => true])
        ->post("{$c}/reseau/mutuelles/{$this->mutual->id}/test", ['is_test' => 1, 'note' => 'Mutuelle d’essai de la plateforme']);
    expect((bool) $this->mutual->fresh()->is_test)->toBeTrue();
    expect(collect($this->mutual->fresh()->status_history)->last()['action'])->toBe('test_on');
    $this->mutual = $this->mutual->fresh();
    app('auth')->forgetGuards();

    asAdmin()->get($this->base)->assertSee('Environnement de test')->assertSee('Charger les formules d’exemple');
    asAdmin()->post($this->base . '/exemples')->assertSessionHas('success', fn ($m) => str_contains($m, '6 formule'));
    asAdmin()->post($this->base . '/exemples')->assertSessionHas('success', fn ($m) => str_contains($m, 'déjà'));
    $this->mutual->run(function () {
        $fam = Guarantee::where('code', 'EX-FAMILLE')->first();
        expect($fam->is_example)->toBeTruthy()->and($fam->catalog_status)->toBe('test');
        $v = $fam->currentVersion;
        expect($v->rules()->pluck('coverage_rate', 'label')->all())->toBe(['Consultation' => 80.0, 'Pharmacie' => 70.0, 'Analyses' => 80.0, 'Hospitalisation' => 80.0, 'Imagerie' => 70.0]);
        expect($v->waitingPeriods()->count())->toBe(18)->and($v->limits()->first()->amount_minor)->toBe(30000);
        // Aucun taux inventé pour les autres formules.
        expect(Guarantee::where('code', 'EX-ESSENTIEL')->first()->currentVersion->rules()->count())->toBe(0);
        expect(Guarantee::where('code', 'EX-ESSENTIEL')->first()->currentVersion->limits()->first()->active)->toBeFalse();
    });

    // Souscription : une formule en brouillon est refusée côté serveur.
    $ids = $this->mutual->run(fn () => [
        Member::create(['member_code' => 'MUT-000901', 'first_name' => 'Paul', 'last_name' => 'Abena', 'birth_date' => '1980-01-01', 'sex' => 'M', 'status' => 'active', 'joined_at' => now()->toDateString()])->id,
        Guarantee::where('code', 'EX-ESSENTIEL')->first()->currentVersion->id,
        Guarantee::where('code', 'EX-FAMILLE')->first()->currentVersion->id,
    ]);
    asAdmin()->post($this->host . '/souscriptions', ['member_id' => $ids[0], 'guarantee_version_id' => $ids[1], 'effective_date' => now()->toDateString()])
        ->assertSessionHasErrors('guarantee_version_id');
    asAdmin()->post($this->host . '/souscriptions', ['member_id' => $ids[0], 'guarantee_version_id' => $ids[2], 'effective_date' => now()->toDateString()])
        ->assertSessionHasNoErrors();
    expect($this->mutual->run(fn () => Subscription::count()))->toBe(1);
});
