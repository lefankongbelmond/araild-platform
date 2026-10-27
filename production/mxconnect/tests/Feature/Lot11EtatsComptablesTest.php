<?php

/*
 * Lot 11 — états comptables SYSCOHADA (balance, grand livre, compte de résultat, bilan) et
 * évolution mensuelle des rapports d'activité. Testé dans une VRAIE base de mutuelle (base
 * dédiée, ou tables préfixées avec MX_TEST_STORAGE=prefix).
 */

use App\Models\Tenant\AccountingEntry;
use App\Models\Tenant\ContributionPayment;
use App\Models\Tenant\ContributionSchedule;
use App\Models\Tenant\Guarantee;
use App\Models\Tenant\GuaranteeVersion;
use App\Models\Tenant\Member;
use App\Models\Tenant\Subscription;
use App\Services\AccountingService;
use App\Services\Tenant\AccountingStatementsService;
use App\Services\Tenant\ActivityReportService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-27 10:00:00'));
    provisionTestMutual();
    // Exercice 2025 : cotisation 50 000, remboursement 20 000 → résultat antérieur 30 000 (non affecté).
    // Exercice 2026 : cotisation 100 000, virement caisse → banque 60 000, facture prestataire 40 000 ;
    // plus une écriture saisie non validée (exclue des états).
    $this->mutual->run(function () {
        $acc = app(AccountingService::class);
        $acc->post('2025-06-10', '571', '7561', 50000, 'Cotisations juin 2025', 'P2025-1');
        $acc->post('2025-06-12', '6011', '571', 20000, 'Remboursement 2025', 'P2025-2');
        $acc->post('2026-01-15', '571', '7561', 100000, 'Cotisations janvier', 'P1');
        $acc->post('2026-02-10', '521', '571', 60000, 'Versement en banque', 'P2');
        $acc->post('2026-02-20', '6041', '521', 40000, 'Facture clinique', 'P3');
        $acc->post('2026-03-05', '6580', '571', 9999, 'Saisie à valider', 'P4')->update(['status' => 'captured']);
    });
    $this->cur = $this->mutual->run(fn () => \App\Models\Central\Currency::on('central')->find(tenant('currency_id')));
    staffUser('tres@equipe.test', ['treasurer_accountant']);
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('calcule une balance générale équilibrée, avec report à nouveau et écritures non validées exclues', function () {
    $b = $this->mutual->run(fn () => app(AccountingStatementsService::class)->balance(Carbon::parse('2026-01-01'), Carbon::parse('2026-10-27')));
    $rows = collect($b['rows'])->keyBy('number');

    expect($b['balanced'])->toBeTrue()
        ->and($b['totals'])->toMatchArray(['open_d' => 30000, 'open_c' => 30000, 'mov_d' => 200000, 'mov_c' => 200000, 'close_d' => 130000, 'close_c' => 130000])
        ->and($rows['12'])->toMatchArray(['open_c' => 30000, 'close_c' => 30000, 'computed' => true])
        ->and($rows['571'])->toMatchArray(['open_d' => 30000, 'mov_d' => 100000, 'mov_c' => 60000, 'close_d' => 70000])
        ->and($rows['521'])->toMatchArray(['close_d' => 20000])
        ->and($rows['7561'])->toMatchArray(['open_c' => 0, 'mov_c' => 100000, 'close_c' => 100000])   // la classe 7 repart de zéro
        ->and($rows->has('6580'))->toBeFalse()                                                         // écriture saisie non validée
        ->and($rows->has('6011'))->toBeFalse();                                                        // compte de gestion 2025 seulement

    // Période en cours d'exercice : ouverture = cumul avant le 1er février.
    $feb = $this->mutual->run(fn () => app(AccountingStatementsService::class)->balance(Carbon::parse('2026-02-01'), Carbon::parse('2026-02-28')));
    $r = collect($feb['rows'])->keyBy('number');
    expect($feb['balanced'])->toBeTrue()
        ->and($r['571'])->toMatchArray(['open_d' => 130000, 'mov_c' => 60000, 'close_d' => 70000])
        ->and($r['7561'])->toMatchArray(['open_c' => 100000, 'mov_c' => 0, 'close_c' => 100000]);
});

it('produit le grand livre, le compte de résultat et un bilan équilibré', function () {
    $s = fn () => app(AccountingStatementsService::class);
    [$ledger, $income, $sheet] = $this->mutual->run(fn () => [
        $s()->ledger(\App\Models\Tenant\Account::byNumber('571'), Carbon::parse('2026-01-01'), Carbon::parse('2026-10-27')),
        $s()->income(Carbon::parse('2026-01-01'), Carbon::parse('2026-10-27')),
        $s()->balanceSheet(Carbon::parse('2026-10-27')),
    ]);

    expect($ledger['opening'])->toBe(30000)
        ->and(array_column($ledger['rows'], 'balance'))->toBe([130000, 70000])
        ->and(array_column($ledger['rows'], 'contra'))->toBe(['7561', '521'])
        ->and($ledger['closing'])->toBe(70000);

    expect($income['total_charges'])->toBe(40000)->and($income['total_produits'])->toBe(100000)->and($income['resultat'])->toBe(60000);

    expect($sheet['balanced'])->toBeTrue()->and($sheet['total_actif'])->toBe(90000)->and($sheet['total_passif'])->toBe(90000)
        ->and(array_column($sheet['actif']['tresorerie'], 'amount', 'number'))->toBe(['521' => 20000, '571' => 70000])
        ->and(collect($sheet['passif']['capitaux'])->pluck('amount', 'number')->all())->toBe(['12' => 30000, '13' => 60000]);

    // Au 31/12/2025 : le résultat 2025 est celui de l'exercice, sans report à nouveau.
    $s2025 = $this->mutual->run(fn () => $s()->balanceSheet(Carbon::parse('2025-12-31')));
    expect($s2025['balanced'])->toBeTrue()->and(collect($s2025['passif']['capitaux'])->pluck('amount', 'number')->all())->toBe(['13' => 30000]);
});

it('affiche les états selon les droits, imprimables et exportables en CSV', function () {
    $h = $this->host; $cur = $this->cur;
    staffUser('audit@equipe.test', ['auditor']);
    staffUser('gest@equipe.test', ['benefits_manager']);
    staffUser('cm@equipe.test', ['community_manager']);

    as_('tres@equipe.test')->get($h . '/comptabilite/balance')->assertOk()->assertSee('Balance générale')->assertSee('Imprimer')
        ->assertSee('Balance équilibrée')->assertSee('Report à nouveau', false)->assertSee($cur->format(130000))
        ->assertSee('1 écriture(s) de la période attendent encore leur validation', false);
    as_('audit@equipe.test')->get($h . '/comptabilite/grand-livre?compte=571')->assertOk()->assertSee('Versement en banque')->assertSee($cur->format(70000));
    as_('audit@equipe.test')->get($h . '/comptabilite/compte-de-resultat')->assertOk()->assertSee('Résultat net : excédent', false)->assertSee($cur->format(60000));
    asAdmin()->get($h . '/comptabilite/bilan')->assertOk()->assertSee('Bilan équilibré', false)->assertSee($cur->format(90000));
    asAdmin()->get($h . '/comptabilite/bilan?exercice=2025')->assertOk()->assertSee('31/12/2025');
    as_('gest@equipe.test')->get($h . '/comptabilite/balance')->assertForbidden();
    as_('cm@equipe.test')->get($h . '/comptabilite/etats/export.csv?etat=balance')->assertForbidden();

    // Dates hors de l'exercice refusées.
    as_('tres@equipe.test')->get($h . '/comptabilite/balance?exercice=2026&du=2025-12-01')->assertSessionHasErrors('du');
    as_('tres@equipe.test')->get($h . '/comptabilite/balance?exercice=2026&du=2026-05-01&au=2026-04-01')->assertSessionHasErrors('au');
    // Après un export CSV, une erreur de dates ramène à l'écran (pas au téléchargement).
    as_('tres@equipe.test')->get($h . '/comptabilite/etats/export.csv?etat=sheet')->assertOk();
    as_('tres@equipe.test')->get($h . '/comptabilite/grand-livre?exercice=2026&compte=571&du=2027-01-01')
        ->assertRedirect($h . '/comptabilite/grand-livre?exercice=2026&compte=571');
    as_('tres@equipe.test')->get($h . '/rapports/export.csv')->assertOk();
    as_('tres@equipe.test')->get($h . '/rapports?periode=libre&du=2026-12-31&au=2026-01-01')->assertRedirect($h . '/rapports');

    $m = fn (int $v) => number_format($v / (10 ** $cur->minor_unit), $cur->minor_unit, ',', '');
    $csv = as_('tres@equipe.test')->get($h . '/comptabilite/etats/export.csv?etat=balance&exercice=2026')->assertOk()->getContent();
    expect($csv)->toContain('"Balance générale"')->toContain('571;Caisse;' . $m(30000) . ';' . $m(0) . ';' . $m(100000) . ';' . $m(60000) . ';' . $m(70000));
    $gl = as_('tres@equipe.test')->get($h . '/comptabilite/etats/export.csv?etat=ledger&compte=tous')->getContent();
    expect($gl)->toContain('"571 Caisse"')->toContain('"521 Banque"')->not->toContain('Saisie à valider');
    expect(as_('tres@equipe.test')->get($h . '/comptabilite/etats/export.csv?etat=sheet')->getContent())->toContain('"Total actif"');
    expect(as_('tres@equipe.test')->get($h . '/comptabilite/etats/export.csv?etat=income')->getContent())->toContain('"RÉSULTAT NET (excédent)";' . $m(60000));
    as_('tres@equipe.test')->get($h . '/comptabilite/etats/export.csv?etat=inconnu')->assertNotFound();
});

it('ajoute l’évolution mensuelle au rapport d’activité', function () {
    $this->mutual->run(function () {
        $g = Guarantee::create(['code' => 'ESS', 'name' => 'Essentielle', 'status' => 'active']);
        $v = GuaranteeVersion::create(['guarantee_id' => $g->id, 'version_no' => 1, 'valid_from' => '2026-01-01', 'base_contribution_minor' => 1000,
            'periodicity' => 'monthly', 'coverage_rate' => 80, 'copay_rate' => 20, 'observation_days' => 0]);
        $mem = Member::create(['member_code' => 'MUT-000901', 'first_name' => 'Awa', 'last_name' => 'Ngono', 'birth_date' => '1990-01-01',
            'sex' => 'F', 'status' => 'active', 'joined_at' => '2026-09-14']);
        $sub = Subscription::create(['member_id' => $mem->id, 'guarantee_version_id' => $v->id, 'contribution_minor' => 1000, 'effective_date' => '2026-09-14',
            'observation_ends_on' => '2026-09-14', 'status' => 'validated', 'captured_by' => 1, 'validated_at' => now()]);
        foreach (['2026-09' => 7000, '2026-10' => 9000] as $period => $amount) {
            $sch = ContributionSchedule::create(['subscription_id' => $sub->id, 'period' => $period, 'year' => 2026,
                'due_minor' => $amount, 'due_date' => $period . '-15', 'status' => 'paid']);
            ContributionPayment::create(['schedule_id' => $sch->id, 'amount_minor' => $amount, 'paid_on' => $period . '-16', 'mode' => 'cash']);
        }
    });

    $monthly = $this->mutual->run(fn () => app(ActivityReportService::class)->monthly(Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31')));
    expect($monthly)->toHaveCount(12)
        ->and($monthly['2026-09'])->toMatchArray(['new' => 1, 'collected' => 7000])
        ->and($monthly['2026-10']['collected'])->toBe(9000)
        ->and($monthly['2026-01']['collected'])->toBe(0);
    // Période libre de plus de 24 mois : les 24 derniers mois seulement.
    expect($this->mutual->run(fn () => app(ActivityReportService::class)->monthly(Carbon::parse('2020-01-01'), Carbon::parse('2026-10-27'))))
        ->toHaveCount(ActivityReportService::MAX_MONTHS)->toHaveKey('2026-10')->not->toHaveKey('2024-10');

    as_('tres@equipe.test')->get($this->host . '/rapports?periode=annee')->assertOk()->assertSee('Évolution mois par mois', false)
        ->assertSee('<svg', false)->assertSee('Cotisations encaissées')->assertSee('Sept. 2026');
    expect(as_('tres@equipe.test')->get($this->host . '/rapports/export.csv?periode=annee')->getContent())
        ->toContain('Mois;"Nouveaux adhérents"');
});
