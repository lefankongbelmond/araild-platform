<?php

/*
 * Lot 13 — cotisations : échéanciers glissants, droits indépendants de la tâche de nuit,
 * encaissement au guichet (partiel, plusieurs échéances, trésorerie, reçu unique), relevé
 * par adhérent, liste et export. Testé dans une VRAIE base de mutuelle (base dédiée, ou
 * tables préfixées avec MX_TEST_STORAGE=prefix).
 */

use App\Models\Tenant\ContributionPayment;
use App\Models\Tenant\ContributionSchedule;
use App\Models\Tenant\FiscalYear;
use App\Models\Tenant\Guarantee;
use App\Models\Tenant\GuaranteeVersion;
use App\Models\Tenant\Member;
use App\Models\Tenant\Subscription;
use App\Models\Tenant\TreasuryMovement;
use App\Services\RightsVerificationService;
use App\Services\ScheduleGenerationService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-27 10:00:00'));
    provisionTestMutual();
    [$this->guarantee, $this->member, $this->sub] = $this->mutual->run(function () {
        $g = Guarantee::create(['code' => 'ESS', 'name' => 'Essentielle', 'status' => 'active']);
        $v = GuaranteeVersion::create(['guarantee_id' => $g->id, 'version_no' => 1, 'valid_from' => '2024-01-01', 'base_contribution_minor' => 5000,
            'periodicity' => 'monthly', 'coverage_rate' => 80, 'copay_rate' => 20, 'observation_days' => 0]);
        $m = Member::create(['member_code' => 'MUT-001300', 'first_name' => 'Awa', 'last_name' => 'Ngono', 'birth_date' => '1990-01-01',
            'sex' => 'F', 'status' => 'active', 'joined_at' => '2025-11-01', 'phone' => '+237690000013']);
        $s = Subscription::create(['member_id' => $m->id, 'guarantee_version_id' => $v->id, 'contribution_minor' => 5000, 'effective_date' => '2025-11-01',
            'observation_ends_on' => '2025-11-01', 'status' => 'validated', 'captured_by' => 1, 'validated_at' => now()]);
        app(ScheduleGenerationService::class)->generate($s);   // 12 échéances : 2025-11 … 2026-10, « à payer »

        return [$g, $m, $s];
    });
    staffUser('tres@equipe.test', ['treasurer_accountant']);
    staffUser('ctrl@equipe.test', ['controller_validator']);
    staffUser('gest@equipe.test', ['benefits_manager']);
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('prolonge les échéanciers (horizon glissant) et compte les retards sans la tâche de nuit', function () {
    $verify = fn () => $this->mutual->run(fn () => app(RightsVerificationService::class)->verify(Member::find($this->member->id), $this->guarantee->id, '2026-10-27'));

    // Échéances passées encore « à payer » (tâche de nuit jamais passée) : droits refusés quand même.
    expect($this->mutual->run(fn () => ContributionSchedule::where('status', 'overdue')->count()))->toBe(0)
        ->and($verify()['reasons'])->toContain('contribution_arrears');

    $r = $this->mutual->run(fn () => app(ScheduleGenerationService::class)->refreshAll());
    expect($r)->toBe(['created' => 12, 'overdue' => 12]);   // 2026-11 … 2027-10 ; 2025-11 … 2026-10 en retard
    expect($this->mutual->run(fn () => app(ScheduleGenerationService::class)->refreshAll()))->toBe(['created' => 0, 'overdue' => 0]);
    expect($this->mutual->run(fn () => ContributionSchedule::orderByDesc('due_date')->value('period')))->toBe('2027-10');

    // Ancienne souscription dont l'échéancier s'était arrêté : rattrapée, retards visibles.
    $old = $this->mutual->run(function () {
        $m = Member::create(['member_code' => 'MUT-001301', 'first_name' => 'Jean', 'last_name' => 'Ancien', 'birth_date' => '1980-01-01', 'sex' => 'M', 'status' => 'active', 'joined_at' => '2024-01-01']);
        $s = Subscription::create(['member_id' => $m->id, 'guarantee_version_id' => $this->sub->guarantee_version_id, 'contribution_minor' => 5000,
            'effective_date' => '2024-01-01', 'status' => 'validated', 'captured_by' => 1, 'validated_at' => now()]);
        app(ScheduleGenerationService::class)->generate($s);
        ContributionSchedule::where('subscription_id', $s->id)->update(['status' => 'paid']);   // 2024 entièrement payé

        return [$m, $s, app(RightsVerificationService::class)->verify($m, $this->guarantee->id, '2026-10-27')['reasons']];
    });
    expect($old[2])->not->toContain('contribution_arrears');   // avant : « à jour » faute d'échéance
    as_('tres@equipe.test')->post($this->host . '/cotisations/actualiser')->assertSessionHas('success');
    $after = $this->mutual->run(fn () => [ContributionSchedule::where('subscription_id', $old[1]->id)->count(),
        app(RightsVerificationService::class)->verify(Member::find($old[0]->id), $this->guarantee->id, '2026-10-27')['reasons']]);
    expect($after[0])->toBe(46)->and($after[1])->toContain('contribution_arrears');   // 01/2024 → 10/2027
    as_('ctrl@equipe.test')->post($this->host . '/cotisations/actualiser')->assertForbidden();
});

it('encaisse au guichet : acompte, plusieurs échéances, trésorerie, reçu unique', function () {
    $h = $this->host; $id = $this->member->id;
    $cur = $this->mutual->run(fn () => \App\Models\Central\Currency::on('central')->find(tenant('currency_id')));
    $m = fn (int $v) => number_format($v / (10 ** $cur->minor_unit), $cur->minor_unit, ',', '');

    as_('tres@equipe.test')->post($h . "/cotisations/adherent/{$id}/encaissement", ['amount' => $m(12000), 'paid_on' => '2026-10-20', 'mode' => 'cash'])
        ->assertRedirect($h . "/cotisations/adherent/{$id}")->assertSessionHas('success');
    [$pays, $schedules, $moves] = $this->mutual->run(fn () => [
        ContributionPayment::orderBy('id')->get(),
        ContributionSchedule::orderBy('due_date')->limit(3)->get()->map(fn ($s) => [$s->period, $s->status])->all(),
        TreasuryMovement::all(),
    ]);
    expect($pays->pluck('amount_minor')->all())->toBe([5000, 5000, 2000])
        ->and($pays->pluck('collection_ref')->unique())->toHaveCount(1)
        ->and($pays->pluck('receipt_path')->unique())->toHaveCount(1)
        ->and($schedules)->toBe([['2025-11', 'paid'], ['2025-12', 'paid'], ['2026-01', 'to_pay']])
        ->and($moves)->toHaveCount(1)
        ->and($moves[0]->only('direction', 'source', 'mode', 'amount_minor', 'linked_reference'))
        ->toBe(['direction' => 'inflow', 'source' => 'contribution', 'mode' => 'cash', 'amount_minor' => 12000, 'linked_reference' => $pays[0]->collection_ref]);

    // Reçu unique : périodes, acompte, reste à payer.
    $receipt = as_('tres@equipe.test')->get($h . "/recus/{$pays[0]->id}")->assertOk()->assertHeader('Content-Security-Policy')->getContent();
    expect($receipt)->toContain($pays[0]->collection_ref)->toContain('2025-11')->toContain('2026-01')->toContain('(acompte)')
        ->toContain($cur->format(48000));   // 60 000 − 12 000

    // Contrôles.
    as_('tres@equipe.test')->post($h . "/cotisations/adherent/{$id}/encaissement", ['amount' => $m(3000), 'paid_on' => '2026-10-21', 'mode' => 'bank'])
        ->assertSessionHasErrors('reference');
    as_('tres@equipe.test')->post($h . "/cotisations/adherent/{$id}/encaissement", ['amount' => $m(999999), 'paid_on' => '2026-10-21', 'mode' => 'cash'])
        ->assertSessionHas('error', fn ($e) => str_contains($e, 'dépasse le reste à payer'));
    as_('tres@equipe.test')->post($h . "/cotisations/adherent/{$id}/encaissement", ['amount' => $m(1000), 'paid_on' => '2026-11-02', 'mode' => 'cash'])
        ->assertSessionHas('error', 'La date d’encaissement ne peut pas être dans le futur.');
    $this->mutual->run(fn () => FiscalYear::create(['label' => '2025', 'start_date' => '2025-01-01', 'end_date' => '2025-12-31', 'status' => 'closed']));
    as_('tres@equipe.test')->post($h . "/cotisations/adherent/{$id}/encaissement", ['amount' => $m(1000), 'paid_on' => '2025-12-30', 'mode' => 'cash'])
        ->assertSessionHas('error', fn ($e) => str_contains($e, 'clôturé'));
    as_('ctrl@equipe.test')->post($h . "/cotisations/adherent/{$id}/encaissement", ['amount' => $m(1000), 'paid_on' => '2026-10-21', 'mode' => 'cash'])->assertForbidden();

    // Banque avec référence : solde de l'échéance entamée.
    as_('tres@equipe.test')->post($h . "/cotisations/adherent/{$id}/encaissement", ['amount' => $m(3000), 'paid_on' => '2026-10-21', 'mode' => 'bank', 'reference' => 'VIR-889'])
        ->assertSessionHas('success');
    expect($this->mutual->run(fn () => [ContributionSchedule::where('period', '2026-01')->value('status'), (int) TreasuryMovement::where('mode', 'bank')->sum('amount_minor')]))
        ->toBe(['paid', 3000]);

    // La trésorerie se comptabilise : caisse 12 000 + banque 3 000 en produit de cotisations.
    as_('tres@equipe.test')->post($h . '/comptabilite/poster-tresorerie')->assertSessionHas('success');
    $b = collect($this->mutual->run(fn () => app(\App\Services\Tenant\AccountingStatementsService::class)->balance(Carbon::parse('2026-01-01'), Carbon::parse('2026-10-27')))['rows'])->keyBy('number');
    expect($b['571']['close_d'])->toBe(12000)->and($b['521']['close_d'])->toBe(3000)->and($b['7561']['close_c'])->toBe(15000);

    // Relevé de l'adhérent.
    as_('tres@equipe.test')->get($h . "/cotisations/adherent/{$id}")->assertOk()->assertSee('Relevé de cotisations', false)->assertSee('VIR-889')
        ->assertSee($cur->format(15000))->assertSee('Enregistrer l’encaissement', false);
    as_('ctrl@equipe.test')->get($h . "/cotisations/adherent/{$id}")->assertOk()->assertDontSee('Enregistrer l’encaissement', false);
    as_('gest@equipe.test')->get($h . "/cotisations/adherent/{$id}")->assertForbidden();
});

it('liste les cotisations avec totaux, recherche, solde rapide et export CSV', function () {
    $h = $this->host;
    $this->mutual->run(fn () => app(ScheduleGenerationService::class)->refreshAll());
    as_('tres@equipe.test')->post($h . "/cotisations/adherent/{$this->member->id}/encaissement", ['amount' => '2000', 'paid_on' => '2026-10-20', 'mode' => 'cash']);
    $cur = $this->mutual->run(fn () => \App\Models\Central\Currency::on('central')->find(tenant('currency_id')));
    $partial = $this->mutual->run(fn () => ContributionSchedule::where('period', '2025-11')->firstOrFail());

    as_('tres@equipe.test')->get($h . '/cotisations')->assertOk()->assertSee('En retard')->assertSee($cur->format(58000))->assertSee('acompte')
        ->assertSee('Actualiser les échéanciers', false);
    as_('tres@equipe.test')->get($h . '/cotisations?q=Ngono')->assertSee('MUT-001300');
    as_('tres@equipe.test')->get($h . '/cotisations?q=Personne')->assertDontSee('MUT-001300');
    as_('ctrl@equipe.test')->get($h . '/cotisations')->assertOk()->assertDontSee('Actualiser les échéanciers', false)->assertDontSee('Solder');
    as_('gest@equipe.test')->get($h . '/cotisations')->assertForbidden();

    // « Solder » depuis la liste : le reste de l'échéance entamée, avec trésorerie.
    as_('tres@equipe.test')->post($h . "/cotisations/{$partial->id}/paiement", ['mode' => 'cash'])->assertSessionHas('success');
    expect($this->mutual->run(fn () => [$partial->fresh()->status, (int) $partial->payments()->sum('amount_minor'), (int) TreasuryMovement::sum('amount_minor')]))
        ->toBe(['paid', 5000, 5000]);

    $csv = as_('ctrl@equipe.test')->get($h . '/cotisations/export.csv?status=overdue')->assertOk()->getContent();
    expect($csv)->toContain('"Reste à payer"')->toContain('+237690000013')->toContain('2025-12')->not->toContain(';2025-11;');

    // Rapport d'activité : arriérés = reste dû (acomptes déduits).
    as_('tres@equipe.test')->post($h . "/cotisations/adherent/{$this->member->id}/encaissement", ['amount' => '1500', 'paid_on' => '2026-10-20', 'mode' => 'cash']);
    $overdue = $this->mutual->run(fn () => app(\App\Services\Tenant\ActivityReportService::class)->build(Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31'))['contributions']['overdue']);
    expect($overdue)->toBe(11 * 5000 - 1500);
});
