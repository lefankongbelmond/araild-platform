<?php

/*
 * Mission « Mutuelle paramétrable », L3 (2026-11-19) : accords préalables (dont l'urgence sans accord,
 * scénario 11), médecin-conseil, pharmacie (ordonnance facturée deux fois, scénario 5).
 * VRAIE base de mutuelle (ou tables préfixées avec MX_TEST_STORAGE=prefix).
 * Les taux et montants sont des PARAMÈTRES DE TEST, pas des valeurs du logiciel.
 */

use App\Models\Tenant\ActType;
use App\Models\Tenant\Alert;
use App\Models\Tenant\CareClaim;
use App\Models\Tenant\CoverageLimit;
use App\Models\Tenant\CoverageRule;
use App\Models\Tenant\Dispensation;
use App\Models\Tenant\Guarantee;
use App\Models\Tenant\GuaranteeVersion;
use App\Models\Tenant\Medicine;
use App\Models\Tenant\Member;
use App\Models\Tenant\MutualSetting;
use App\Models\Tenant\Preauthorization;
use App\Models\Tenant\Prestation;
use App\Models\Tenant\RightsCheck;
use App\Models\Tenant\ServiceCategory;
use App\Models\Tenant\Subscription;
use App\Models\Tenant\User;
use App\Services\AccountingRefusal;
use App\Services\ClaimCaptureService;
use App\Services\Tenant\PharmacyService;
use App\Services\Tenant\PreauthorizationService;

beforeEach(function () {
    provisionTestMutual();
    staffUser('saisie@equipe.test', ['benefits_manager']);
    staffUser('controle@equipe.test', ['controller_validator']);
    staffUser('medecin@equipe.test', ['medical_advisor']);
    staffUser('tresor@equipe.test', ['treasurer_accountant']);
    $this->ids = $this->mutual->run(function () {
        $cat = fn ($c) => ServiceCategory::where('code', $c)->value('id');
        $g = Guarantee::create(['code' => 'FAM', 'name' => 'Famille', 'status' => 'active']);
        $v = GuaranteeVersion::create(['guarantee_id' => $g->id, 'version_no' => 1, 'valid_from' => now()->subYear()->toDateString(),
            'base_contribution_minor' => 5000, 'periodicity' => 'monthly', 'coverage_rate' => 0, 'copay_rate' => 0, 'observation_days' => 0]);
        $hosp = ActType::create(['code' => 'HOS', 'label' => 'Hospitalisation programmée', 'category' => 'Hospitalisation', 'service_category_id' => $cat('hospitalization')]);
        $img = ActType::create(['code' => 'IMG', 'label' => 'Scanner', 'category' => 'Imagerie', 'service_category_id' => $cat('ct_scan')]);
        $pha = ActType::create(['code' => 'PHA', 'label' => 'Médicaments sur ordonnance', 'category' => 'Pharmacie', 'service_category_id' => $cat('pharmacy')]);
        CoverageRule::create(['guarantee_version_id' => $v->id, 'service_category_id' => $cat('hospitalization'), 'label' => 'Hospitalisation',
            'beneficiary_category' => 'all', 'coverage_rate' => 80, 'requires_authorization' => true, 'authorization_review' => 'medical']);
        CoverageRule::create(['guarantee_version_id' => $v->id, 'service_category_id' => $cat('ct_scan'), 'label' => 'Scanner',
            'beneficiary_category' => 'all', 'coverage_rate' => 70, 'requires_authorization' => true, 'authorization_review' => 'administrative', 'authorization_above_minor' => 50000]);
        $rp = CoverageRule::create(['guarantee_version_id' => $v->id, 'service_category_id' => $cat('pharmacy'), 'label' => 'Pharmacie',
            'beneficiary_category' => 'all', 'coverage_rate' => 70]);
        CoverageLimit::create(['guarantee_version_id' => $v->id, 'label' => 'Pharmacie', 'rule_keys' => [$rp->rule_key], 'scope' => 'year', 'holder' => 'family', 'amount_minor' => 30000]);
        $m = Member::create(['member_code' => 'MUT-000700', 'first_name' => 'Awa', 'last_name' => 'Ngono', 'birth_date' => '1990-04-02',
            'sex' => 'F', 'status' => 'active', 'joined_at' => now()->subYear()->toDateString()]);
        Subscription::create(['member_id' => $m->id, 'guarantee_version_id' => $v->id, 'contribution_minor' => 5000,
            'effective_date' => now()->subYear()->toDateString(), 'status' => 'validated', 'captured_by' => 1]);
        $para = Medicine::create(['code' => 'PARA', 'label' => 'Paracétamol 500 mg', 'reference_price_minor' => 100]);
        $amox = Medicine::create(['code' => 'AMOX', 'label' => 'Amoxicilline 500 mg', 'reference_price_minor' => 250, 'max_quantity' => 10]);
        $vit = Medicine::create(['code' => 'VIT', 'label' => 'Vitamine C', 'reference_price_minor' => 50, 'eligible' => false]);

        return compact('g', 'v', 'hosp', 'img', 'pha', 'm', 'para', 'amox', 'vit');
    });
    $this->conv = conventioned();
    tenancy()->end();
    staffUser('accueil@csi.test', ['provider'], ['care_provider_id' => $this->conv->care_provider_id]);
    $this->pid = $this->conv->care_provider_id;
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

function l3User(string $email): User
{
    return User::where('email', $email)->firstOrFail();
}

function l3Capture(int $actId, int $total, array $opt = []): array
{
    $t = test();

    return $t->mutual->run(function () use ($t, $actId, $total, $opt) {
        $claim = CareClaim::create(['member_id' => $t->ids['m']->id, 'opened_on' => now()->toDateString(), 'status' => 'open']);

        return app(ClaimCaptureService::class)->capture($claim, ['act_type_id' => $actId, 'provider_id' => $t->pid, 'care_date' => now()->toDateString(),
            'total_minor' => $total] + $opt, l3User($opt['by'] ?? 'saisie@equipe.test'), $opt['source'] ?? 'mutual');
    });
}

function l3Check(): int
{
    as_('accueil@csi.test')->post(test()->host . '/prestataire/verifier', ['code' => 'MUT-000700'])->assertOk()->assertSee('DROITS ACTIFS');

    return test()->mutual->run(fn () => RightsCheck::latest('id')->value('id'));
}

it('scénario 11 : urgence sans accord préalable — soins jamais bloqués, régularisation par le médecin-conseil', function () {
    // Sans accord ni urgence : AUTORISATION ADMINISTRATIVE REQUISE, rien n'est enregistré.
    $r = l3Capture($this->ids['hosp']->id, 200000);
    expect($r['ok'])->toBeFalse()->and($r['status'])->toBe('authorization')->and($r['reasons'])->toBe(['authorization_required']);
    expect($this->mutual->run(fn () => Prestation::count()))->toBe(0);

    // Urgence : prise en charge enregistrée, marquée à régulariser ; demande « urgence » ouverte ; signal.
    $u = l3Capture($this->ids['hosp']->id, 200000, ['emergency' => true]);
    expect($u['ok'])->toBeTrue()->and($u['authorization_missing'])->toBeTrue()->and($u['prestation']->mutual_part_minor)->toBe(160000);
    $this->mutual->run(function () use ($u) {
        $p = Preauthorization::firstOrFail();
        expect($p->emergency)->toBeTrue()->and($p->status)->toBe('requested')->and($p->prestation_id)->toBe($u['prestation']->id)->and($p->review_type)->toBe('medical');
        expect(Alert::where('object_id', $u['prestation']->id)->get()->pluck('details.code')->all())->toContain('emergency_without_authorization');
    });

    // Validation de la prestation bloquée tant que la régularisation n'est pas examinée.
    $claimId = $u['prestation']->care_claim_id;
    as_('controle@equipe.test')->post("{$this->host}/recours/{$claimId}/prestations/{$u['prestation']->id}/validation")->assertSessionHas('error', fn ($m) => str_contains($m, 'régularisation'));

    // Examen médical : le contrôleur ne peut pas décider ; le médecin-conseil accorde.
    $pa = $this->mutual->run(fn () => Preauthorization::first());
    as_('controle@equipe.test')->post("{$this->host}/accords-prealables/{$pa->id}/decision", ['decision' => 'approved'])->assertSessionHas('error', fn ($m) => str_contains($m, 'médecin-conseil'));
    as_('medecin@equipe.test')->post("{$this->host}/accords-prealables/{$pa->id}/decision", ['decision' => 'approved', 'note' => 'Urgence justifiée'])->assertSessionHas('success');
    $this->mutual->run(function () use ($u) {
        $p = Preauthorization::first();
        expect($p->status)->toBe('used')->and($p->number)->toBe('AUT-' . now()->year . '-00001');
        expect((bool) Prestation::find($u['prestation']->id)->authorization_missing)->toBeFalse();
    });
    as_('controle@equipe.test')->post("{$this->host}/recours/{$claimId}/prestations/{$u['prestation']->id}/validation")->assertSessionHas('success');
});

it('accord préalable demandé au portail : contrôles automatiques, 4 yeux, numéro, usage unique, montant accordé', function () {
    $check = l3Check();
    as_('accueil@csi.test')->get("{$this->host}/prestataire/accords/demander/{$check}")->assertOk()->assertSee('Demander un accord préalable');
    as_('accueil@csi.test')->post("{$this->host}/prestataire/accords/demander/{$check}", ['beneficiary' => 'member', 'act_type_id' => $this->ids['img']->id,
        'planned_on' => now()->addDays(3)->toDateString(), 'total' => '100 000', 'clinical_info' => 'Bilan de contrôle demandé par le spécialiste.'])
        ->assertRedirect()->assertSessionHas('success');
    $pa = $this->mutual->run(fn () => Preauthorization::firstOrFail());
    expect($pa->review_type)->toBe('administrative')->and($pa->auto_checks['status'])->toBe('active')->and($pa->estimated_mutual_minor)->toBe(70000);

    // Justification médicale : visible du contrôleur, pas du trésorier (qui n'a pas accès).
    as_('controle@equipe.test')->get("{$this->host}/accords-prealables/{$pa->id}")->assertOk()->assertSee('Bilan de contrôle')->assertSee('DROITS ACTIFS');
    as_('tresor@equipe.test')->get("{$this->host}/accords-prealables")->assertForbidden();

    // Déclarer sans accord encore décidé : refusé avec un lien vers la demande.
    as_('accueil@csi.test')->post("{$this->host}/prestataire/declarer/{$check}", ['beneficiary' => 'member', 'act_type_id' => $this->ids['img']->id,
        'care_date' => now()->toDateString(), 'total' => '100000'])->assertSessionHas('rights_block', ['authorization_required']);

    // Rejet sans motif refusé ; accord avec une part mutuelle plafonnée à 60 000.
    as_('controle@equipe.test')->post("{$this->host}/accords-prealables/{$pa->id}/decision", ['decision' => 'rejected'])->assertSessionHas('error');
    as_('controle@equipe.test')->post("{$this->host}/accords-prealables/{$pa->id}/decision", ['decision' => 'approved', 'approved' => '60000'])->assertSessionHas('success');
    as_('accueil@csi.test')->get("{$this->host}/prestataire/accords")->assertOk()->assertSee('AUT-' . now()->year . '-00001')->assertSee('Accordée');

    // Soins : l'accord s'applique (part mutuelle ≤ 60 000), puis il est « utilisé » et ne sert plus.
    as_('accueil@csi.test')->post("{$this->host}/prestataire/declarer/{$check}", ['beneficiary' => 'member', 'act_type_id' => $this->ids['img']->id,
        'care_date' => now()->toDateString(), 'total' => '100000'])->assertRedirect("{$this->host}/prestataire/prises-en-charge");
    $p = $this->mutual->run(fn () => Prestation::latest('id')->first());
    expect($p->mutual_part_minor)->toBe(60000)->and($p->preauthorization_id)->toBe($pa->id);
    expect($this->mutual->run(fn () => Preauthorization::find($pa->id)->status))->toBe('used');
    expect(l3Capture($this->ids['img']->id, 100000, ['provider' => 1])['status'])->toBe('authorization');

    // Prestation rejetée : l'accord redevient utilisable.
    as_('controle@equipe.test')->post("{$this->host}/recours/{$p->care_claim_id}/prestations/{$p->id}/rejet", ['note' => 'Facture illisible, à refaire.']);
    expect($this->mutual->run(fn () => Preauthorization::find($pa->id)->only(['status', 'prestation_id'])))->toBe(['status' => 'approved', 'prestation_id' => null]);

    // En dessous du seuil (50 000) : pas d'accord exigé.
    expect(l3Capture($this->ids['img']->id, 40000)['ok'])->toBeTrue();
});

it('le demandeur ne décide jamais sa propre demande ; le prestataire peut retirer une demande en attente', function () {
    $svc = fn () => app(PreauthorizationService::class);
    $this->mutual->run(function () use ($svc) {
        $p = $svc()->request(['member' => Member::first(), 'dependent' => null, 'provider_id' => $this->pid, 'act_type_id' => $this->ids['img']->id,
            'planned_on' => now()->addDay()->toDateString(), 'estimated_minor' => 90000, 'clinical_info' => null], l3User('controle@equipe.test'), 'mutual');
        expect(fn () => $svc()->decide($p, l3User('controle@equipe.test'), 'approved', null))->toThrow(AccountingRefusal::class, '4 yeux');
    });
    tenancy()->end();
    $check = l3Check();
    as_('accueil@csi.test')->post("{$this->host}/prestataire/accords/demander/{$check}", ['beneficiary' => 'member', 'act_type_id' => $this->ids['hosp']->id,
        'planned_on' => now()->addDays(10)->toDateString(), 'total' => '300000']);
    $id = $this->mutual->run(fn () => Preauthorization::latest('id')->value('id'));
    as_('accueil@csi.test')->post("{$this->host}/prestataire/accords/{$id}/retirer")->assertSessionHas('success');
    expect($this->mutual->run(fn () => Preauthorization::find($id)->status))->toBe('cancelled');
});

it('scénario 5 : une même ordonnance ne peut pas être facturée deux fois (même dans une autre pharmacie)', function () {
    $this->mutual->run(function () {
        $ph = app(PharmacyService::class);
        $m = Member::first();
        $rx = ['reference' => 'ORD-2026/0042', 'prescriber' => 'Dr Mbarga', 'prescribed_on' => now()->subDays(2)->toDateString(), 'renewals_allowed' => 0];
        $lines = [['medicine_id' => $this->ids['para']->id, 'quantity_prescribed' => 20, 'quantity_dispensed' => 20, 'unit_price_minor' => 100]];
        $a = $ph->dispense($m, null, $this->pid, $this->ids['pha']->id, $rx, $lines, l3User('accueil@csi.test'));
        expect($a['ok'])->toBeTrue()->and($a['prestation']->mutual_part_minor)->toBe(1400);
        // Même ordonnance (n° écrit autrement), autre pharmacie : refusée.
        expect(fn () => $ph->dispense($m, null, 9999, $this->ids['pha']->id, ['reference' => 'ord 2026 0042'] + $rx, $lines, l3User('accueil@csi.test')))
            ->toThrow(AccountingRefusal::class, 'Ordonnance déjà utilisée');
        expect(Dispensation::count())->toBe(1)->and(Prestation::count())->toBe(1);

        // Délivrance partielle puis complément ; au-delà : refus. Renouvellement autorisé : une 2e fois.
        $rx2 = ['reference' => 'ORD-77', 'prescriber' => 'Dr Mbarga', 'prescribed_on' => now()->toDateString(), 'renewals_allowed' => 1];
        $l = fn ($q) => [['medicine_id' => $this->ids['amox']->id, 'quantity_prescribed' => 10, 'quantity_dispensed' => $q, 'unit_price_minor' => 250]];
        expect($ph->dispense($m, null, $this->pid, $this->ids['pha']->id, $rx2, $l(6), l3User('accueil@csi.test'))['ok'])->toBeTrue();
        expect($ph->dispense($m, null, $this->pid, $this->ids['pha']->id, $rx2, $l(10), l3User('accueil@csi.test'))['ok'])->toBeTrue();   // renouvellement
        expect(fn () => $ph->dispense($m, null, $this->pid, $this->ids['pha']->id, $rx2, $l(5), l3User('accueil@csi.test')))
            ->toThrow(AccountingRefusal::class, 'il reste 4');
        expect(fn () => $ph->dispense($m, null, $this->pid, $this->ids['pha']->id, $rx2, $l(11), l3User('accueil@csi.test')))
            ->toThrow(AccountingRefusal::class, 'dépasse la quantité prescrite');

        // Prestation rejetée : la quantité est libérée.
        Prestation::whereKey($a['prestation']->id)->update(['status' => 'rejected']);
        expect($ph->dispense($m, null, $this->pid, $this->ids['pha']->id, $rx, $lines, l3User('accueil@csi.test'))['ok'])->toBeTrue();
    });
});

it('pharmacie : médicament hors liste à la charge du patient, prix de référence, ordonnance périmée, délivrance rapprochée', function () {
    $this->mutual->run(function () {
        $ph = app(PharmacyService::class);
        $m = Member::first();
        MutualSetting::put('prescription_validity_days', '30');
        MutualSetting::put('dispensation_watch_days', '7');
        $rx = fn ($ref, $d = 0) => ['reference' => $ref, 'prescriber' => 'CSI Akono', 'prescribed_on' => now()->subDays($d)->toDateString(), 'renewals_allowed' => 0];

        expect(fn () => $ph->dispense($m, null, $this->pid, $this->ids['pha']->id, $rx('OLD-1', 45),
            [['medicine_id' => $this->ids['para']->id, 'quantity_prescribed' => 1, 'quantity_dispensed' => 1, 'unit_price_minor' => 100]], l3User('accueil@csi.test')))
            ->toThrow(AccountingRefusal::class, 'périmée');

        // Vitamine hors liste (1 000) + paracétamol au-dessus du prix de référence (20 × 150 = 3 000).
        $r = $ph->dispense($m, null, $this->pid, $this->ids['pha']->id, $rx('A-1'), [
            ['medicine_id' => $this->ids['vit']->id, 'quantity_prescribed' => 10, 'quantity_dispensed' => 10, 'unit_price_minor' => 100],
            ['medicine_id' => $this->ids['para']->id, 'quantity_prescribed' => 20, 'quantity_dispensed' => 20, 'unit_price_minor' => 150],
        ], l3User('accueil@csi.test'));
        expect($r['excluded'])->toBe(1000)->and($r['prestation']->total_minor)->toBe(4000)
            ->and($r['prestation']->mutual_part_minor)->toBe(2100)->and($r['prestation']->beneficiary_part_minor)->toBe(1900);
        expect(Alert::where('object_id', $r['prestation']->id)->get()->pluck('details.code')->all())->toContain('above_reference_price');

        // Amoxicilline : 12 délivrées, maximum 10 par ordonnance → 2 à la charge du patient.
        $b = $ph->dispense($m, null, $this->pid, $this->ids['pha']->id, $rx('B-1'),
            [['medicine_id' => $this->ids['amox']->id, 'quantity_prescribed' => 12, 'quantity_dispensed' => 12, 'unit_price_minor' => 250]], l3User('accueil@csi.test'));
        expect($b['excluded'])->toBe(500);

        // Même médicament redélivré dans les 7 jours (autre ordonnance) : signal, pas un refus.
        $c = $ph->dispense($m, null, $this->pid, $this->ids['pha']->id, $rx('C-1'),
            [['medicine_id' => $this->ids['para']->id, 'quantity_prescribed' => 10, 'quantity_dispensed' => 10, 'unit_price_minor' => 100]], l3User('accueil@csi.test'));
        expect($c['ok'])->toBeTrue();
        expect(Alert::where('object_id', $c['prestation']->id)->get()->pluck('details.code')->all())->toContain('close_redispensing');
    });
});

it('pharmacie par le portail, registre et politique au back-office, droits du médecin-conseil', function () {
    $check = l3Check();
    as_('accueil@csi.test')->get("{$this->host}/prestataire/pharmacie/{$check}")->assertOk()->assertSee('Délivrer une ordonnance')->assertSee('Vitamine C (hors liste)');
    $form = ['beneficiary' => 'member', 'act_type_id' => $this->ids['pha']->id, 'reference' => 'P-555', 'prescriber' => 'Dr Atangana',
        'prescribed_on' => now()->toDateString(), 'renewals_allowed' => 0,
        'lines' => [['medicine_id' => $this->ids['para']->id, 'prescribed' => 10, 'dispensed' => 10, 'unit_price' => '100'], ['medicine_id' => '', 'prescribed' => '', 'dispensed' => '', 'unit_price' => '']]];
    as_('accueil@csi.test')->post("{$this->host}/prestataire/pharmacie/{$check}", $form)->assertRedirect("{$this->host}/prestataire/prises-en-charge")
        ->assertSessionHas('success', fn ($m) => str_contains($m, 'Délivrance enregistrée'));
    as_('accueil@csi.test')->post("{$this->host}/prestataire/pharmacie/{$check}", $form)->assertSessionHasErrors('lines');

    as_('medecin@equipe.test')->get("{$this->host}/pharmacie")->assertOk()->assertSee('P-555')->assertSee('Paracétamol 500 mg');
    as_('medecin@equipe.test')->get("{$this->host}/pharmacie/politique")->assertForbidden();
    as_('tresor@equipe.test')->get("{$this->host}/pharmacie")->assertForbidden();
    asAdmin()->get("{$this->host}/pharmacie/politique")->assertOk()->assertSee('Médicaments pris en charge');
    asAdmin()->post("{$this->host}/pharmacie/politique", ['prescription_validity_days' => 60])->assertSessionHas('success');
    asAdmin()->post("{$this->host}/pharmacie/medicaments", ['m' => [$this->ids['vit']->id => ['eligible' => 1, 'max_quantity' => 5]]])->assertSessionHas('success');
    $this->mutual->run(function () {
        expect(MutualSetting::int('prescription_validity_days'))->toBe(60);
        expect(Medicine::find($this->ids['vit']->id)->only(['eligible', 'max_quantity']))->toBe(['eligible' => true, 'max_quantity' => 5]);
    });

    // Médecin-conseil : rôle incompatible avec trésorier ; voit les recours et le détail médical.
    as_('medecin@equipe.test')->get("{$this->host}/recours")->assertOk();
    expect(\App\Support\TenantAccess::allows('capacite.donnees-sante', 'medical_advisor'))->toBeTrue()
        ->and(\App\Support\TenantAccess::allows('preauth.decide', 'treasurer_accountant'))->toBeFalse();
    expect(collect(config('mxconnect.incompatible_role_pairs'))->contains(['medical_advisor', 'treasurer_accountant']))->toBeTrue();
});
