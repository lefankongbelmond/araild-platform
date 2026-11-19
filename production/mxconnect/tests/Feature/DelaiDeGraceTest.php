<?php

/*
 * Délai de grâce avant suspension des droits (2026-11-24), réglable par la mutuelle.
 * Le retard financier reste daté de l'échéance ; seule la suspension des droits attend la fin du délai.
 * VRAIE base de mutuelle (ou tables préfixées).
 */

use App\Models\Tenant\ContributionSchedule;
use App\Models\Tenant\Guarantee;
use App\Models\Tenant\GuaranteeVersion;
use App\Models\Tenant\Member;
use App\Models\Tenant\MutualSetting;
use App\Models\Tenant\Subscription;
use App\Services\RightsVerificationService;
use App\Services\Tenant\CoverageEngine;
use App\Services\Tenant\CoverageService;
use App\Services\Tenant\PilotageService;
use App\Support\ArrearsPolicy;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-10 09:00:00'));
    provisionTestMutual();
    staffUser('tresor@equipe.test', ['treasurer_accountant']);
    $this->ids = $this->mutual->run(function () {
        $g = Guarantee::create(['code' => 'ESS', 'name' => 'Essentielle', 'status' => 'active']);
        GuaranteeVersion::create(['guarantee_id' => $g->id, 'version_no' => 1, 'valid_from' => '2026-01-01', 'base_contribution_minor' => 3000,
            'periodicity' => 'monthly', 'coverage_rate' => 80, 'copay_rate' => 20, 'observation_days' => 0]);
        $m = Member::create(['member_code' => 'MUT-GRACE', 'first_name' => 'Awa', 'last_name' => 'Grace', 'birth_date' => '1990-01-01', 'sex' => 'F', 'status' => 'active', 'joined_at' => '2026-01-01']);
        $s = Subscription::create(['member_id' => $m->id, 'guarantee_version_id' => $g->currentVersion()->first()->id, 'contribution_minor' => 3000,
            'effective_date' => '2026-11-01', 'observation_ends_on' => '2026-11-01', 'status' => 'validated', 'captured_by' => 1]);
        ContributionSchedule::create(['subscription_id' => $s->id, 'period' => '2026-11', 'year' => 2026, 'due_minor' => 3000, 'due_date' => '2026-11-01', 'status' => 'overdue']);

        return ['g' => $g->id, 'm' => $m->id];
    });
    tenancy()->end();
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

function graceState(): array
{
    $t = test();

    return $t->mutual->run(function () use ($t) {
        $m = Member::find($t->ids['m']);

        return [
            'engine' => app(CoverageEngine::class)->membership($m, null, now()->toDateString())['status'],
            'rights' => app(RightsVerificationService::class)->verify($m, $t->ids['g'], now()->toDateString())['reasons'],
            'situation' => app(CoverageService::class)->situation($m, null, 1),
            'suspended' => app(PilotageService::class)->build(now()->startOfYear(), now()->endOfYear(), [])['members']['suspended'],
        ];
    });
}

it('sans délai de grâce : suspension dès le lendemain de l’échéance (comportement d’origine)', function () {
    $s = graceState();
    expect($s['engine'])->toBe('suspended')->and($s['rights'])->toContain('contribution_arrears')->and($s['suspended'])->toBe(1);
    expect($s['situation']['overdue_count'])->toBe(1)->and($s['situation']['grace_until'])->toBeNull();
});

it('avec un délai de grâce : droits maintenus jusqu’à sa fin, retard financier toujours visible, puis suspension', function () {
    $this->mutual->run(fn () => MutualSetting::put(ArrearsPolicy::KEY, '15'));

    // 10/11 : échéance du 01/11 impayée, dans le délai → droits maintenus jusqu'au 16/11.
    $s = graceState();
    expect($s['engine'])->toBe('active')->and($s['rights'])->not->toContain('contribution_arrears')->and($s['suspended'])->toBe(0);
    expect($s['situation']['overdue_count'])->toBe(1)->and($s['situation']['grace_until'])->toBe('2026-11-16');

    // Dernier jour du délai : toujours ouverts. Le lendemain : suspendus.
    $this->travelTo(Carbon::parse('2026-11-16 18:00:00'));
    expect(graceState()['engine'])->toBe('active');
    $this->travelTo(Carbon::parse('2026-11-17 08:00:00'));
    $s = graceState();
    expect($s['engine'])->toBe('suspended')->and($s['rights'])->toContain('contribution_arrears')->and($s['suspended'])->toBe(1)
        ->and($s['situation']['grace_until'])->toBeNull();

    // Une fois l'échéance réglée : droits rouverts.
    $this->mutual->run(fn () => ContributionSchedule::query()->update(['status' => 'paid']));
    expect(graceState()['engine'])->toBe('active');
});

it('se règle par l’administrateur ; les autres le consultent', function () {
    $h = $this->host;
    asAdmin()->get($h . '/cotisations')->assertSee('Règle de suspension');
    asAdmin()->get($h . '/cotisations/regle-de-suspension')->assertOk()->assertSee('dès le lendemain', false);
    asAdmin()->post($h . '/cotisations/regle-de-suspension', ['grace_days' => 500])->assertSessionHasErrors('grace_days');
    asAdmin()->post($h . '/cotisations/regle-de-suspension', ['grace_days' => 10])->assertSessionHas('success');
    expect($this->mutual->run(fn () => ArrearsPolicy::graceDays()))->toBe(10);
    as_('tresor@equipe.test')->get($h . '/cotisations/regle-de-suspension')->assertOk()->assertSee('10 jour(s)', false)->assertDontSee('Enregistrer');
    as_('tresor@equipe.test')->post($h . '/cotisations/regle-de-suspension', ['grace_days' => 0])->assertForbidden();
    asAdmin()->post($h . '/cotisations/regle-de-suspension', ['grace_days' => 0])->assertSessionHas('success', fn ($m) => str_contains($m, 'retiré'));
    expect($this->mutual->run(fn () => MutualSetting::get(ArrearsPolicy::KEY)))->toBeNull();
});
