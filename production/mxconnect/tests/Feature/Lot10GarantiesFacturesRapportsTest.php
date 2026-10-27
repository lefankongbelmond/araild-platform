<?php

/*
 * Lot 10 — plafonds et exclusions des garanties, registre des factures des prestataires,
 * rapports d'activité. Testé dans une VRAIE base de mutuelle (base dédiée, ou tables préfixées
 * avec MX_TEST_STORAGE=prefix).
 */

use App\Models\Tenant\ActType;
use App\Models\Tenant\Alert;
use App\Models\Tenant\CareClaim;
use App\Models\Tenant\Ceiling;
use App\Models\Tenant\ContributionPayment;
use App\Models\Tenant\ContributionSchedule;
use App\Models\Tenant\Guarantee;
use App\Models\Tenant\GuaranteeVersion;
use App\Models\Tenant\Member;
use App\Models\Tenant\Prestation;
use App\Models\Tenant\ProviderConvention;
use App\Models\Tenant\ProviderInvoice;
use App\Models\Tenant\Subscription;

beforeEach(function () {
    provisionTestMutual();
    [$this->guarantee, $this->member, $this->act, $this->act2, $this->sub] = $this->mutual->run(function () {
        $g = Guarantee::create(['code' => 'ESS', 'name' => 'Essentielle', 'status' => 'active']);
        $v = GuaranteeVersion::create(['guarantee_id' => $g->id, 'version_no' => 1, 'valid_from' => '2026-01-01', 'base_contribution_minor' => 1000,
            'periodicity' => 'monthly', 'coverage_rate' => 80, 'copay_rate' => 20, 'observation_days' => 0]);
        $m = Member::create(['member_code' => 'MUT-000900', 'first_name' => 'Paul', 'last_name' => 'Essomba', 'birth_date' => '1985-05-05',
            'sex' => 'M', 'status' => 'active', 'joined_at' => now()->toDateString()]);
        $s = Subscription::create(['member_id' => $m->id, 'guarantee_version_id' => $v->id, 'contribution_minor' => 1000, 'effective_date' => '2026-01-01',
            'observation_ends_on' => '2026-01-01', 'status' => 'validated', 'captured_by' => 1, 'validated_at' => now()]);

        return [$g, $m, ActType::create(['code' => 'HOSP', 'label' => 'Hospitalisation', 'category' => 'hospitalisation']),
            ActType::create(['code' => 'DENT', 'label' => 'Prothèse dentaire', 'category' => 'dentaire']), $s];
    });
    staffUser('gest@equipe.test', ['benefits_manager']);
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

/** Prestation saisie au back-office par le gestionnaire. */
function captureCare(int $actId, string $total): \Illuminate\Testing\TestResponse
{
    $claim = test()->mutual->run(fn () => CareClaim::create(['member_id' => test()->member->id, 'opened_on' => now()->toDateString(), 'status' => 'open']));

    return as_('gest@equipe.test')->post(test()->host . "/recours/{$claim->id}/prestations", ['act_type_id' => $actId, 'provider_id' => 999,
        'care_date' => now()->toDateString(), 'total' => $total, 'channel' => 'tiers_payant']);
}

// --- Plafonds et exclusions ---

it('applique un plafond et une exclusion saisis à l’écran', function () {
    $url = $this->host . "/garanties/{$this->guarantee->id}";
    asAdmin()->get($url)->assertOk()->assertSee('Plafonds et exclusions')->assertSee('Hospitalisation');
    asAdmin()->post($url . '/conditions', [
        'exclusions' => [$this->act2->id],
        'ceilings'   => [['act_type_id' => $this->act->id, 'amount' => '50000', 'period' => 'per_act'], ['act_type_id' => '', 'amount' => '', 'period' => 'annual']],
    ])->assertRedirect($url)->assertSessionHas('success', 'Plafonds et exclusions enregistrés.');
    $v = $this->mutual->run(fn () => GuaranteeVersion::with('ceilings')->first());
    expect($v->exclusions)->toBe([$this->act2->id])->and($v->ceilings)->toHaveCount(1)
        ->and($v->ceilings[0]->ceiling_minor)->toBe($this->mutual->run(fn () => \App\Models\Central\Currency::on('central')->find(tenant('currency_id'))->toMinor(50000)));

    // Acte exclu : prise en charge refusée.
    captureCare($this->act2->id, '30000')->assertSessionHas('rights_block', ['act_excluded']);
    // Plafond par acte : 100 000 × 80 % = 80 000 → plafonné ; alerte « plafond atteint ».
    captureCare($this->act->id, '100000')->assertSessionHas('success');
    $p = $this->mutual->run(fn () => Prestation::firstOrFail());
    expect($p->mutual_part_minor)->toBe($v->ceilings[0]->ceiling_minor)
        ->and($p->beneficiary_part_minor)->toBe($p->total_minor - $p->mutual_part_minor)
        ->and($this->mutual->run(fn () => Alert::where('details->code', 'ceiling_reached')->exists()))->toBeTrue();
    asAdmin()->get($url)->assertSee('Actes non couverts : <b>Prothèse dentaire</b>', false);
});

it('ouvre une nouvelle version pour des conditions changées sur une version déjà utilisée', function () {
    $url = $this->host . "/garanties/{$this->guarantee->id}";
    captureCare($this->act->id, '10000');                     // la V1 est maintenant verrouillée
    asAdmin()->post($url . '/conditions', ['exclusions' => [$this->act2->id]])->assertSessionHasErrors('effective_from');
    asAdmin()->post($url . '/conditions', ['ceilings' => [['act_type_id' => '', 'amount' => '1', 'period' => 'annual'], ['act_type_id' => '', 'amount' => '2', 'period' => 'annual']],
        'effective_from' => now()->addDay()->toDateString()])->assertSessionHasErrors('ceilings');
    asAdmin()->post($url . '/conditions', ['exclusions' => [$this->act2->id], 'ceilings' => [['act_type_id' => '', 'amount' => '200000', 'period' => 'annual']],
        'effective_from' => now()->addDays(2)->toDateString()])->assertRedirect($url);
    [$v1, $v2] = $this->mutual->run(fn () => GuaranteeVersion::with('ceilings')->orderBy('version_no')->get()->all());
    expect($v1->exclusions)->toBeNull()->and($v1->ceilings)->toHaveCount(0)
        ->and($v2->exclusions)->toBe([$this->act2->id])->and($v2->ceilings)->toHaveCount(1)->and((float) $v2->coverage_rate)->toBe(80.0);
    as_('gest@equipe.test')->post($url . '/conditions', ['exclusions' => []])->assertForbidden();
});

// --- Registre des factures des prestataires ---

/** Facture d'un prestataire conventionné (saisie par son compte portail). */
function providerInvoice(int $amount = 8000): ProviderInvoice
{
    asAdmin()->post(test()->host . '/parametres/prestataires', ['mode' => 'new', 'name' => 'CSI de Mvog-Mbi', 'type' => 'health_center'])->assertRedirect();
    $conv = test()->mutual->run(fn () => ProviderConvention::latest('id')->firstOrFail());
    $prov = staffUser('accueil' . random_int(10, 99) . '@csi.test', ['provider'], ['care_provider_id' => $conv->care_provider_id]);

    return test()->mutual->run(function () use ($conv, $prov, $amount) {
        $claim = CareClaim::create(['member_id' => test()->member->id, 'opened_on' => now()->toDateString(), 'status' => 'open']);

        return ProviderInvoice::create(['provider_id' => $conv->care_provider_id, 'care_claim_id' => $claim->id, 'amount_minor' => $amount,
            'status' => 'captured', 'captured_by' => $prov->id, 'provider_reference' => 'F-77']);
    });
}

it('rassemble les factures des prestataires : paiement par le trésorier, rejet motivé, export', function () {
    $inv = providerInvoice();
    staffUser('tres@equipe.test', ['treasurer_accountant']);
    staffUser('ctrl@equipe.test', ['controller_validator']);
    staffUser('audit@equipe.test', ['auditor']);
    staffUser('cm@equipe.test', ['community_manager']);
    $h = $this->host;

    as_('tres@equipe.test')->get($h . '/factures-prestataires')->assertOk()->assertSee('CSI de Mvog-Mbi')->assertSee('F-77')
        ->assertSee('Enregistrer le paiement')->assertDontSee('Rejeter cette facture');
    as_('audit@equipe.test')->get($h . '/factures-prestataires')->assertOk()->assertDontSee('Enregistrer le paiement')->assertDontSee('Rejeter cette facture');
    as_('cm@equipe.test')->get($h . '/factures-prestataires')->assertForbidden();
    $csv = as_('tres@equipe.test')->get($h . '/factures-prestataires/export.csv')->assertOk();
    expect($csv->headers->get('Content-Type'))->toContain('text/csv')->and($csv->getContent())->toContain('CSI de Mvog-Mbi')->toContain('MUT-000900');

    // Rejet motivé (contrôleur) ; le trésorier ne peut pas rejeter.
    as_('tres@equipe.test')->post($h . "/factures-prestataires/{$inv->id}/rejet", ['note' => 'Montant erroné'])->assertForbidden();
    as_('ctrl@equipe.test')->post($h . "/factures-prestataires/{$inv->id}/rejet", ['note' => ''])->assertSessionHasErrors('note');
    as_('ctrl@equipe.test')->post($h . "/factures-prestataires/{$inv->id}/rejet", ['note' => 'Montant différent de la prestation validée'])->assertSessionHas('success');
    $inv = $this->mutual->run(fn () => $inv->fresh());
    expect($inv->status)->toBe('rejected')->and($inv->decision_note)->toBe('Montant différent de la prestation validée')->and($inv->decided_at)->not->toBeNull();
    as_('tres@equipe.test')->get($h . '/factures-prestataires?statut=rejetees')->assertSee('Montant différent de la prestation validée');
    as_('ctrl@equipe.test')->post($h . "/factures-prestataires/{$inv->id}/rejet", ['note' => 'Encore une fois'])->assertStatus(422);

    // Une seconde facture, payée depuis le registre.
    $inv2 = providerInvoice(5000);
    as_('tres@equipe.test')->from($h . '/factures-prestataires')->post($h . "/recours/{$inv2->care_claim_id}/facture/{$inv2->id}/paiement")
        ->assertRedirect($h . '/factures-prestataires');
    $inv2 = $this->mutual->run(fn () => $inv2->fresh());
    expect($inv2->status)->toBe('paid')->and($inv2->decided_at)->not->toBeNull();
    as_('tres@equipe.test')->get($h . '/factures-prestataires?statut=payees')->assertSee('F-77')->assertSee('Payée le');
});

it('montre le motif du rejet au prestataire', function () {
    $inv = providerInvoice();
    staffUser('ctrl@equipe.test', ['controller_validator']);
    as_('ctrl@equipe.test')->post($this->host . "/factures-prestataires/{$inv->id}/rejet", ['note' => 'Pièce illisible, merci de renvoyer'])->assertSessionHas('success');
    $prov = $this->mutual->run(fn () => \App\Models\Tenant\User::find($inv->captured_by));
    as_($prov->email)->get($this->host . '/prestataire/factures')->assertOk()->assertSee('Rejetée')->assertSee('Pièce illisible, merci de renvoyer');
});

// --- Rapports d'activité ---

it('produit un rapport d’activité réel, imprimable et exportable, selon les droits', function () {
    $this->mutual->run(function () {
        $sch = ContributionSchedule::create(['subscription_id' => $this->sub->id, 'period' => now()->format('Y-m'), 'year' => now()->year,
            'due_minor' => 10000, 'due_date' => now()->toDateString(), 'status' => 'paid']);
        ContributionPayment::create(['schedule_id' => $sch->id, 'amount_minor' => 10000, 'paid_on' => now()->toDateString(), 'mode' => 'cash']);
    });
    captureCare($this->act->id, '5000');
    $p = $this->mutual->run(fn () => Prestation::firstOrFail());
    staffUser('ctrl@equipe.test', ['controller_validator']);
    as_('ctrl@equipe.test')->post($this->host . "/recours/{$p->care_claim_id}/prestations/{$p->id}/validation");
    staffUser('tres@equipe.test', ['treasurer_accountant']);
    staffUser('cm@equipe.test', ['community_manager']);
    $cur = $this->mutual->run(fn () => \App\Models\Central\Currency::on('central')->find(tenant('currency_id')));
    $h = $this->host;

    $html = as_('gest@equipe.test')->get($h . '/rapports')->assertOk()->assertSee('Rapport d’activité', false)->assertSee('Imprimer')
        ->assertSee($cur->format(10000))->assertSee('100,0 %')->assertSee('Hospitalisation')->getContent();
    expect($html)->toContain($cur->format($this->mutual->run(fn () => Prestation::first()->mutual_part_minor)));
    as_('tres@equipe.test')->get($h . '/rapports')->assertOk()->assertDontSee('Hospitalisation')->assertSee('Réservé aux rôles qui voient le détail médical');
    as_('cm@equipe.test')->get($h . '/rapports')->assertForbidden();
    as_('tres@equipe.test')->get($h . '/rapports?periode=libre&du=2026-12-31&au=2026-01-01')->assertSessionHasErrors('au');

    $csv = as_('gest@equipe.test')->get($h . '/rapports/export.csv?periode=annee')->assertOk()->getContent();
    expect($csv)->toContain('"Adhérents actifs";1')->toContain('"Prestations validées";1')->toContain('Par acte');
    expect(as_('tres@equipe.test')->get($h . '/rapports/export.csv')->getContent())->not->toContain('Par acte');
});
