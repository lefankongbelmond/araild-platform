<?php

/*
 * Lot 14 — annulation d'un encaissement (demande motivée, décision « 4 yeux », échéances
 * rouvertes, trésorerie et comptabilité contre-passées, reçu « ANNULÉ ») et droit d'adhésion
 * encaissé au guichet. Testé dans une VRAIE base de mutuelle (base dédiée, ou tables
 * préfixées avec MX_TEST_STORAGE=prefix).
 */

use App\Models\Tenant\CollectionCancellation;
use App\Models\Tenant\ContributionPayment;
use App\Models\Tenant\ContributionSchedule;
use App\Models\Tenant\Guarantee;
use App\Models\Tenant\GuaranteeVersion;
use App\Models\Tenant\Member;
use App\Models\Tenant\Subscription;
use App\Models\Tenant\TreasuryMovement;
use App\Services\ScheduleGenerationService;
use App\Services\Tenant\AccountingStatementsService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-27 10:00:00'));
    provisionTestMutual();
    [$this->member, $this->sub] = $this->mutual->run(function () {
        $g = Guarantee::create(['code' => 'ESS', 'name' => 'Essentielle', 'status' => 'active']);
        $v = GuaranteeVersion::create(['guarantee_id' => $g->id, 'version_no' => 1, 'valid_from' => '2026-01-01', 'base_contribution_minor' => 5000,
            'periodicity' => 'monthly', 'coverage_rate' => 80, 'copay_rate' => 20, 'observation_days' => 0, 'membership_fee_minor' => 2500]);
        $m = Member::create(['member_code' => 'MUT-001400', 'first_name' => 'Ada', 'last_name' => 'Mballa', 'birth_date' => '1992-02-02',
            'sex' => 'F', 'status' => 'active', 'joined_at' => '2026-09-01']);
        $s = Subscription::create(['member_id' => $m->id, 'guarantee_version_id' => $v->id, 'contribution_minor' => 5000, 'effective_date' => '2026-09-01',
            'observation_ends_on' => '2026-09-01', 'status' => 'validated', 'captured_by' => 1, 'validated_at' => now()]);

        return [$m, $s];
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

function encaisser(string $amount, string $date = '2026-10-20', string $mode = 'cash'): string
{
    as_('tres@equipe.test')->post(test()->host . '/cotisations/adherent/' . test()->member->id . '/encaissement', ['amount' => $amount, 'paid_on' => $date, 'mode' => $mode]);

    return test()->mutual->run(fn () => ContributionPayment::latest('id')->value('collection_ref'));
}

it('appelle le droit d’adhésion à la validation et l’encaisse avant les cotisations (compte 7011)', function () {
    $h = $this->host;
    $this->mutual->run(fn () => app(ScheduleGenerationService::class)->generate(Subscription::with('guaranteeVersion')->find($this->sub->id)));
    $first = $this->mutual->run(fn () => ContributionSchedule::orderBy('due_date')->orderByDesc('kind')->get()->take(2)->map(fn ($s) => [$s->label(), $s->due_minor])->all());
    expect($first)->toBe([['Droit d’adhésion', 2500], ['2026-09', 5000]]);
    expect($this->mutual->run(fn () => ContributionSchedule::where('kind', 'membership_fee')->count()))->toBe(1);
    $this->mutual->run(fn () => app(ScheduleGenerationService::class)->generate(Subscription::with('guaranteeVersion')->find($this->sub->id)));
    expect($this->mutual->run(fn () => ContributionSchedule::where('kind', 'membership_fee')->count()))->toBe(1);   // jamais en double

    // 4 000 : droit d'adhésion 2 500 d'abord, puis 1 500 d'acompte sur septembre ; deux mouvements de trésorerie.
    encaisser('4000');
    $moves = $this->mutual->run(fn () => TreasuryMovement::get()->map(fn ($m) => [$m->source, $m->amount_minor])->sortBy(0)->values()->all());   // tri PHP (ENUM trié par rang en SQL)
    expect($moves)->toBe([['contribution', 1500], ['membership_fee', 2500]]);
    as_('tres@equipe.test')->post($h . '/comptabilite/poster-tresorerie');
    $b = collect($this->mutual->run(fn () => app(AccountingStatementsService::class)->balance(Carbon::parse('2026-01-01'), Carbon::parse('2026-10-27')))['rows'])->keyBy('number');
    expect($b['7011']['close_c'])->toBe(2500)->and($b['7561']['close_c'])->toBe(1500);

    $receipt = as_('tres@equipe.test')->get($h . '/recus/' . $this->mutual->run(fn () => ContributionPayment::value('id')))->getContent();
    expect($receipt)->toContain('Droit d’adhésion');
    as_('tres@equipe.test')->get($h . "/cotisations/adherent/{$this->member->id}")->assertSee('Droit d’adhésion', false);

    // Rapport d'activité : droits d'adhésion à part des cotisations.
    $r = $this->mutual->run(fn () => app(\App\Services\Tenant\ActivityReportService::class)->build(Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31'))['contributions']);
    expect($r['collected'])->toBe(1500)->and($r['fees'])->toBe(2500);
});

it('appelle le droit d’adhésion d’une souscription antérieure, sur demande', function () {
    $h = $this->host; $id = $this->member->id;
    $this->mutual->run(fn () => app(ScheduleGenerationService::class)->extend(Subscription::with('guaranteeVersion')->find($this->sub->id)));   // sans droit d'adhésion
    as_('tres@equipe.test')->get($h . "/cotisations/adherent/{$id}")->assertSee('Appeler le droit d’adhésion', false);
    as_('ctrl@equipe.test')->post($h . "/cotisations/adherent/{$id}/droit-adhesion")->assertForbidden();
    as_('tres@equipe.test')->post($h . "/cotisations/adherent/{$id}/droit-adhesion")->assertSessionHas('success');
    as_('tres@equipe.test')->post($h . "/cotisations/adherent/{$id}/droit-adhesion")->assertSessionHas('error');
    expect($this->mutual->run(fn () => ContributionSchedule::where('kind', 'membership_fee')->first()->only('due_minor', 'period')))
        ->toBe(['due_minor' => 2500, 'period' => 'ADH']);
    as_('tres@equipe.test')->get($h . "/cotisations/adherent/{$id}")->assertDontSee('Appeler le droit d’adhésion', false);
});

it('annule un encaissement : demande motivée, décision par une autre personne, tout est rétabli', function () {
    $h = $this->host; $id = $this->member->id;
    $this->mutual->run(fn () => app(ScheduleGenerationService::class)->extend(Subscription::with('guaranteeVersion')->find($this->sub->id)));
    $ref = encaisser('7000');   // septembre payé, octobre acompte 2 000
    as_('tres@equipe.test')->post($h . '/comptabilite/poster-tresorerie');

    // Demande : trésorier seulement, motif obligatoire, une seule en attente.
    as_('ctrl@equipe.test')->post($h . "/cotisations/encaissements/{$ref}/annulation", ['reason' => 'Erreur de saisie'])->assertForbidden();
    as_('tres@equipe.test')->post($h . "/cotisations/encaissements/{$ref}/annulation", ['reason' => ''])->assertSessionHasErrors('reason');
    as_('tres@equipe.test')->post($h . "/cotisations/encaissements/{$ref}/annulation", ['reason' => 'Montant saisi sur le mauvais adhérent'])->assertSessionHas('success');
    as_('tres@equipe.test')->post($h . "/cotisations/encaissements/{$ref}/annulation", ['reason' => 'Doublon de demande'])->assertSessionHas('error');
    $c = $this->mutual->run(fn () => CollectionCancellation::firstOrFail());
    expect($c->only('status', 'amount_minor', 'member_id'))->toBe(['status' => 'pending', 'amount_minor' => 7000, 'member_id' => $id]);

    // Visible des décideurs ; le demandeur ne décide pas.
    as_('ctrl@equipe.test')->get($h . '/cotisations')->assertSee('Annulations d’encaissement à décider (1)', false)->assertSee('Montant saisi sur le mauvais adhérent', false);
    as_('ctrl@equipe.test')->get($h . "/cotisations/adherent/{$id}")->assertSee('Valider l’annulation', false);
    expect(fn () => $this->mutual->run(fn () => app(\App\Services\Tenant\ContributionCollectionService::class)->approveCancellation($c, (int) $c->requested_by)))
        ->toThrow(\App\Services\AccountingRefusal::class, 'une autre personne');
    as_('tres@equipe.test')->post($h . "/cotisations/annulations/{$c->id}/validation")->assertForbidden();

    as_('ctrl@equipe.test')->post($h . "/cotisations/annulations/{$c->id}/validation")->assertSessionHas('success');
    as_('ctrl@equipe.test')->post($h . "/cotisations/annulations/{$c->id}/validation")->assertSessionHas('error');   // déjà décidée

    [$state, $paid, $moves, $trashed] = $this->mutual->run(fn () => [
        ContributionSchedule::whereIn('period', ['2026-09', '2026-10'])->orderBy('period')->pluck('status')->all(),
        (int) ContributionPayment::sum('amount_minor'),
        TreasuryMovement::orderBy('id')->get()->map(fn ($m) => [$m->direction, $m->source, $m->amount_minor, $m->linked_reference])->all(),
        ContributionPayment::onlyTrashed()->count(),
    ]);
    expect($state)->toBe(['overdue', 'overdue'])->and($paid)->toBe(0)->and($trashed)->toBe(2)
        ->and($moves)->toBe([['inflow', 'contribution', 7000, $ref], ['outflow', 'contribution', 7000, 'ANN-' . $ref]]);

    // Comptabilité : produit et caisse contre-passés une fois la trésorerie comptabilisée.
    as_('tres@equipe.test')->post($h . '/comptabilite/poster-tresorerie');
    $b = collect($this->mutual->run(fn () => app(AccountingStatementsService::class)->balance(Carbon::parse('2026-01-01'), Carbon::parse('2026-10-27')))['rows'])->keyBy('number');
    expect($b['7561'])->toMatchArray(['mov_d' => 7000, 'mov_c' => 7000, 'close_c' => 0])->and($b['571']['close_d'])->toBe(0);

    // Reçu marqué ANNULÉ ; relevé : encaissement barré, totaux nets.
    $pid = $this->mutual->run(fn () => ContributionPayment::onlyTrashed()->value('id'));
    expect(as_('tres@equipe.test')->get($h . "/recus/{$pid}")->assertOk()->getContent())->toContain('ANNULÉ')->toContain('Montant saisi sur le mauvais adhérent');
    as_('tres@equipe.test')->get($h . "/cotisations/adherent/{$id}")->assertSee('Annulé le', false)->assertSee('line-through', false)
        ->assertDontSee('Demander l’annulation', false);
    as_('ctrl@equipe.test')->get($h . '/cotisations')->assertDontSee('Annulations d’encaissement à décider', false);
});

it('refuse une demande d’annulation avec un motif : l’encaissement est maintenu', function () {
    $h = $this->host; $id = $this->member->id;
    $this->mutual->run(fn () => app(ScheduleGenerationService::class)->extend(Subscription::with('guaranteeVersion')->find($this->sub->id)));
    $ref = encaisser('5000');
    as_('tres@equipe.test')->post($h . "/cotisations/encaissements/{$ref}/annulation", ['reason' => 'Je pensais à une erreur']);
    $c = $this->mutual->run(fn () => CollectionCancellation::firstOrFail());
    asAdmin()->post($h . "/cotisations/annulations/{$c->id}/refus", ['note' => ''])->assertSessionHasErrors('note');
    asAdmin()->post($h . "/cotisations/annulations/{$c->id}/refus", ['note' => 'Le reçu signé confirme le paiement'])->assertSessionHas('success');
    expect($this->mutual->run(fn () => [$c->fresh()->status, (int) ContributionPayment::sum('amount_minor'), TreasuryMovement::count()]))->toBe(['rejected', 5000, 1]);
    as_('tres@equipe.test')->get($h . "/cotisations/adherent/{$id}")->assertSee('Annulation refusée le', false)->assertSee('Le reçu signé confirme le paiement', false)
        ->assertSee('Demander l’annulation', false);   // une nouvelle demande reste possible
    as_('gest@equipe.test')->post($h . "/cotisations/annulations/{$c->id}/validation")->assertForbidden();
});
