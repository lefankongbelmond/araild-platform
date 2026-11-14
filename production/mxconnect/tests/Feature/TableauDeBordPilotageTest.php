<?php

/*
 * Mission « Mutuelle paramétrable », L5 (2026-11-20) : tableau de bord de pilotage.
 * Indicateurs réels, filtres, droits, export Excel. VRAIE base de mutuelle (ou tables préfixées).
 */

use App\Models\Tenant\ActType;
use App\Models\Tenant\CareClaim;
use App\Models\Tenant\ContributionPayment;
use App\Models\Tenant\ContributionSchedule;
use App\Models\Tenant\CoverageLimit;
use App\Models\Tenant\CoverageRule;
use App\Models\Tenant\Dependent;
use App\Models\Tenant\Guarantee;
use App\Models\Tenant\GuaranteeVersion;
use App\Models\Tenant\Member;
use App\Models\Tenant\Prestation;
use App\Models\Tenant\ServiceCategory;
use App\Models\Tenant\Subscription;
use App\Services\Tenant\PilotageService;

beforeEach(function () {
    provisionTestMutual();
    staffUser('tresor@equipe.test', ['treasurer_accountant']);
    staffUser('agent@equipe.test', ['enrollment_agent']);
    staffUser('auditeur@equipe.test', ['auditor']);
    $this->ids = $this->mutual->run(function () {
        $cat = fn ($c) => ServiceCategory::where('code', $c)->value('id');
        $fam = Guarantee::create(['code' => 'FAM', 'name' => 'Famille', 'status' => 'active']);
        $vf = GuaranteeVersion::create(['guarantee_id' => $fam->id, 'version_no' => 1, 'valid_from' => now()->subYear()->toDateString(),
            'base_contribution_minor' => 5000, 'periodicity' => 'monthly', 'coverage_rate' => 0, 'copay_rate' => 0, 'observation_days' => 0]);
        $ess = Guarantee::create(['code' => 'ESS', 'name' => 'Essentielle', 'status' => 'active']);
        $ve = GuaranteeVersion::create(['guarantee_id' => $ess->id, 'version_no' => 1, 'valid_from' => now()->subYear()->toDateString(),
            'base_contribution_minor' => 3000, 'periodicity' => 'monthly', 'coverage_rate' => 70, 'copay_rate' => 30, 'observation_days' => 0]);
        $cons = ActType::create(['code' => 'CONS', 'label' => 'Consultation', 'category' => 'Consultations', 'service_category_id' => $cat('consultation')]);
        $rule = CoverageRule::create(['guarantee_version_id' => $vf->id, 'service_category_id' => $cat('consultation'), 'label' => 'Consultations', 'beneficiary_category' => 'all', 'coverage_rate' => 80]);
        CoverageLimit::create(['guarantee_version_id' => $vf->id, 'label' => 'Plafond familial', 'rule_keys' => null, 'scope' => 'year', 'holder' => 'family', 'amount_minor' => 100000]);

        $mk = function ($code, $status, $v, $subStatus = 'validated') {
            $m = Member::create(['member_code' => $code, 'first_name' => $code, 'last_name' => 'Test', 'birth_date' => '1990-01-01', 'sex' => 'F', 'status' => $status, 'joined_at' => now()->subYear()->toDateString()]);
            $s = Subscription::create(['member_id' => $m->id, 'guarantee_version_id' => $v->id, 'contribution_minor' => 5000, 'effective_date' => now()->subYear()->toDateString(), 'status' => $subStatus, 'captured_by' => 1]);

            return [$m, $s];
        };
        [$a, $sa] = $mk('MUT-A', 'active', $vf);          // actif, cotisation payée
        [$b, $sb] = $mk('MUT-B', 'active', $vf);          // échéance échue impayée → suspendu
        [$c] = $mk('MUT-C', 'left', $ve);                 // sorti → expiré
        [$d] = $mk('MUT-D', 'active', $ve, 'captured');   // en attente
        Dependent::create(['member_id' => $a->id, 'first_name' => 'Junior', 'last_name' => 'Test', 'birth_date' => '2015-01-01', 'sex' => 'M', 'relationship' => 'child', 'status' => 'active']);
        $due = now()->startOfYear()->addDays(10)->toDateString();
        $paid = ContributionSchedule::create(['subscription_id' => $sa->id, 'period' => 'P1', 'year' => now()->year, 'due_minor' => 5000, 'due_date' => $due, 'status' => 'paid']);
        ContributionPayment::create(['schedule_id' => $paid->id, 'amount_minor' => 5000, 'paid_on' => $due, 'mode' => 'cash']);
        ContributionSchedule::create(['subscription_id' => $sb->id, 'period' => 'P1', 'year' => now()->year, 'due_minor' => 5000, 'due_date' => $due, 'status' => 'to_pay']);

        $claim = CareClaim::create(['member_id' => $a->id, 'opened_on' => $due, 'status' => 'open']);
        $base = ['care_claim_id' => $claim->id, 'act_type_id' => $cons->id, 'guarantee_version_id' => $vf->id, 'care_date' => $due, 'coverage_rule_id' => $rule->id];
        Prestation::create($base + ['provider_id' => 11, 'total_minor' => 100000, 'mutual_part_minor' => 80000, 'beneficiary_part_minor' => 20000, 'copay_minor' => 20000, 'status' => 'validated']);
        Prestation::create($base + ['provider_id' => 12, 'total_minor' => 10000, 'mutual_part_minor' => 5000, 'beneficiary_part_minor' => 5000, 'copay_minor' => 2000, 'status' => 'validated']);
        Prestation::create($base + ['provider_id' => 11, 'total_minor' => 7000, 'mutual_part_minor' => 5600, 'beneficiary_part_minor' => 1400, 'status' => 'captured']);
        Prestation::create($base + ['provider_id' => 11, 'total_minor' => 9000, 'mutual_part_minor' => 0, 'beneficiary_part_minor' => 9000, 'status' => 'rejected']);

        return compact('fam', 'ess', 'cat');
    });
    tenancy()->end();
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('calcule les indicateurs réels de la mission (adhésions, cotisations, prestations, plafonds, sinistralité)', function () {
    $r = $this->mutual->run(fn () => app(PilotageService::class)->build(now()->startOfYear(), now()->endOfYear(), []));
    expect($r['members'])->toMatchArray(['total' => 4, 'active' => 1, 'suspended' => 1, 'expired' => 1, 'pending' => 1, 'dependents' => 1, 'beneficiaries' => 3]);
    expect($r['contributions'])->toMatchArray(['expected' => 10000, 'paid_on_expected' => 5000, 'collected' => 5000, 'rate' => 50.0, 'overdue' => 5000]);
    expect($r['prestations'])->toMatchArray(['billed' => 117000, 'covered' => 85000, 'copay' => 22000, 'validated' => 2, 'pending' => 1, 'rejected' => 1, 'loss_ratio' => 1700.0]);
    expect($r['by_formula'][0])->toMatchArray(['label' => 'Famille', 'count' => 2, 'mutual' => 85000]);
    expect($r['by_rule'][0])->toMatchArray(['label' => 'Consultations', 'mutual' => 85000]);
    // Plafond familial 100 000 : 85 000 validés + 5 600 en attente = 90 600 → ≥ 80 %, pas épuisé.
    expect($r['limits'][0])->toMatchArray(['label' => 'Plafond familial', 'holders' => 1, 'over80' => 1, 'full' => 0, 'consumed' => 90600]);

    // Filtres : prestataire, statut, formule sans soins.
    $p = $this->mutual->run(fn () => app(PilotageService::class)->build(now()->startOfYear(), now()->endOfYear(), ['provider_id' => 12]));
    expect($p['prestations']['covered'])->toBe(5000)->and($p['prestations']['validated'])->toBe(1);
    $s = $this->mutual->run(fn () => app(PilotageService::class)->build(now()->startOfYear(), now()->endOfYear(), ['status' => 'rejected']));
    expect($s['prestations'])->toMatchArray(['covered' => 0, 'validated' => 0, 'pending' => 0, 'rejected' => 1]);
    expect(array_sum(array_column($s['monthly'], 'mutual')))->toBe(0)->and(array_sum(array_column($r['monthly'], 'mutual')))->toBe(85000);
    $e = $this->mutual->run(fn () => app(PilotageService::class)->build(now()->startOfYear(), now()->endOfYear(), ['guarantee_id' => $this->ids['ess']->id]));
    expect($e['members']['total'])->toBe(2)->and($e['prestations']['covered'])->toBe(0);
    // Type de soins : consultation → tout ; pharmacie → rien.
    $cat = $this->mutual->run(fn () => [\App\Models\Tenant\ServiceCategory::where('code', 'consultation')->value('id'), \App\Models\Tenant\ServiceCategory::where('code', '!=', 'consultation')->value('id')]);
    $c1 = $this->mutual->run(fn () => app(PilotageService::class)->build(now()->startOfYear(), now()->endOfYear(), ['category_id' => $cat[0]]));
    $c2 = $this->mutual->run(fn () => app(PilotageService::class)->build(now()->startOfYear(), now()->endOfYear(), ['category_id' => $cat[1]]));
    expect($c1['prestations']['covered'])->toBe(85000)->and($c2['prestations']['covered'])->toBe(0)->and($c2['prestations']['rejected'])->toBe(0);
});

it('affiche le tableau de bord selon les droits et exporte un vrai classeur Excel', function () {
    asAdmin()->get($this->host . '/pilotage?periode=annee')->assertOk()->assertSee('Tableau de bord de pilotage')
        ->assertSee('Consommation par formule')->assertSee('Consommation par garantie')->assertSee('Plafond familial')->assertSee('Adhérent (n°)', false);
    // Trésorier : indicateurs, sans le détail par garantie ni la pharmacie ni le filtre par adhérent.
    as_('tresor@equipe.test')->get($this->host . '/pilotage?periode=annee')->assertOk()->assertSee('Taux de sinistralité')
        ->assertDontSee('Consommation par garantie')->assertDontSee('Consommation pharmaceutique')->assertDontSee('Adhérent (n°)', false);
    as_('auditeur@equipe.test')->get($this->host . '/pilotage')->assertOk();
    as_('agent@equipe.test')->get($this->host . '/pilotage')->assertForbidden();
    // Filtre par adhérent ignoré sans habilitation santé.
    as_('tresor@equipe.test')->get($this->host . '/pilotage?periode=annee&adherent=MUT-A')->assertOk()->assertDontSee('adhérent : MUT-A', false);

    $res = asAdmin()->get($this->host . '/pilotage/export.xlsx?periode=annee&formule=' . $this->ids['fam']->id);
    $res->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $tmp = tempnam(sys_get_temp_dir(), 'x');
    file_put_contents($tmp, $res->getContent());
    $zip = new ZipArchive();
    expect($zip->open($tmp))->toBeTrue();
    $wb = $zip->getFromName('xl/workbook.xml');
    expect($wb)->toContain('Synthèse')->toContain('Par formule')->toContain('Par garantie');
    expect($zip->getFromName('xl/worksheets/sheet1.xml'))->toContain('Taux de recouvrement')->toContain('formule : Famille');
    $zip->close();
    @unlink($tmp);
    // Le trésorier n'a pas les feuilles de santé.
    $t = as_('tresor@equipe.test')->get($this->host . '/pilotage/export.xlsx?periode=annee');
    file_put_contents($tmp, $t->getContent());
    $zip->open($tmp);
    expect($zip->getFromName('xl/workbook.xml'))->not->toContain('Par garantie')->not->toContain('Pharmacie');
    $zip->close();
    @unlink($tmp);
});
