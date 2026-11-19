<?php

/*
 * Changement de formule contrôlé (mission « Mutuelle paramétrable », Partie 5.2 ; 2026-11-23).
 * Demande, « 4 yeux », application à la date d'effet, aucune rétroactivité, ancienneté conservée,
 * échéances, adhérents écartés. VRAIE base de mutuelle (ou tables préfixées).
 */

use App\Models\Tenant\ActType;
use App\Models\Tenant\ContributionPayment;
use App\Models\Tenant\ContributionSchedule;
use App\Models\Tenant\CoverageRule;
use App\Models\Tenant\FormulaMigration;
use App\Models\Tenant\Guarantee;
use App\Models\Tenant\GuaranteeVersion;
use App\Models\Tenant\Member;
use App\Models\Tenant\ServiceCategory;
use App\Models\Tenant\Subscription;
use App\Models\Tenant\User;
use App\Models\Tenant\WaitingPeriod;
use App\Services\AccountingRefusal;
use App\Services\Tenant\CoverageEngine;
use App\Services\Tenant\FormulaMigrationService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-27 10:00:00'));
    provisionTestMutual();
    staffUser('ctrl@equipe.test', ['controller_validator']);
    staffUser('tresor@equipe.test', ['treasurer_accountant']);
    staffUser('audit@equipe.test', ['auditor']);
    $this->ids = $this->mutual->run(function () {
        $cat = ServiceCategory::where('code', 'consultation')->value('id');
        $mk = function (string $code, string $name, int $base, int $rate, int $wait) use ($cat) {
            $g = Guarantee::create(['code' => $code, 'name' => $name, 'status' => 'active', 'catalog_status' => 'active']);
            $v = GuaranteeVersion::create(['guarantee_id' => $g->id, 'version_no' => 1, 'valid_from' => '2025-01-01', 'base_contribution_minor' => $base,
                'periodicity' => 'monthly', 'coverage_rate' => 0, 'copay_rate' => 0, 'observation_days' => 0]);
            $r = CoverageRule::create(['guarantee_version_id' => $v->id, 'service_category_id' => $cat, 'label' => 'Consultations', 'beneficiary_category' => 'all', 'coverage_rate' => $rate]);
            if ($wait) {
                WaitingPeriod::create(['guarantee_version_id' => $v->id, 'rule_key' => $r->rule_key, 'beneficiary_category' => 'all', 'days' => $wait]);
            }

            return [$g, $v];
        };
        [$a, $va] = $mk('ESS', 'Essentielle', 3000, 80, 0);
        [$b, $vb] = $mk('FAM', 'Famille', 5000, 60, 90);
        $act = ActType::create(['code' => 'CONS', 'label' => 'Consultation', 'category' => 'consultation', 'service_category_id' => $cat]);

        $members = [];
        foreach (['MUT-1', 'MUT-2', 'MUT-3'] as $code) {
            $m = Member::create(['member_code' => $code, 'first_name' => 'Awa', 'last_name' => $code, 'birth_date' => '1990-01-01', 'sex' => 'F', 'status' => 'active', 'joined_at' => '2026-01-01']);
            $s = Subscription::create(['member_id' => $m->id, 'guarantee_version_id' => $va->id, 'contribution_minor' => 3000, 'effective_date' => '2026-01-01',
                'observation_ends_on' => '2026-01-01', 'status' => 'validated', 'captured_by' => 1]);
            $oct = ContributionSchedule::create(['subscription_id' => $s->id, 'period' => '2026-10', 'year' => 2026, 'due_minor' => 3000, 'due_date' => '2026-10-01', 'status' => 'paid']);
            ContributionPayment::create(['schedule_id' => $oct->id, 'amount_minor' => 3000, 'paid_on' => '2026-10-01', 'mode' => 'cash']);
            foreach (['2026-11', '2026-12'] as $p) {
                ContributionSchedule::create(['subscription_id' => $s->id, 'period' => $p, 'year' => 2026, 'due_minor' => 3000, 'due_date' => $p . '-01', 'status' => 'to_pay']);
            }
            $members[$code] = $m;
        }
        // MUT-3 a déjà réglé novembre : il sera écarté (le paiement n'est jamais déplacé).
        $nov3 = ContributionSchedule::whereHas('subscription', fn ($q) => $q->where('member_id', $members['MUT-3']->id))->where('period', '2026-11')->first();
        $nov3->update(['status' => 'paid']);
        ContributionPayment::create(['schedule_id' => $nov3->id, 'amount_minor' => 3000, 'paid_on' => '2026-10-20', 'mode' => 'cash']);

        return ['a' => $a->id, 'b' => $b->id, 'act' => $act->id, 'm' => collect($members)->map->id->all()];
    });
    tenancy()->end();
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

function cfUser(string $email): User
{
    return User::where('email', $email)->firstOrFail();
}

it('change de formule à la date d’effet, après validation par une autre personne, sans rien changer au passé', function () {
    $ids = $this->ids;
    $mig = $this->mutual->run(function () use ($ids) {
        $svc = app(FormulaMigrationService::class);
        $prev = $svc->candidates($ids['a'], $ids['b'], '2026-11-01');
        expect(collect($prev['eligible'])->pluck('code')->all())->toBe(['MUT-1', 'MUT-2'])
            ->and($prev['skipped'][0]['code'])->toBe('MUT-3')->and($prev['skipped'][0]['reason'])->toContain('réglée');
        expect($prev['eligible'][0])->toMatchArray(['old_minor' => 3000, 'new_minor' => 5000, 'removed' => 2]);

        // Refus : date passée, même formule.
        expect(fn () => $svc->request(['source_guarantee_id' => $ids['a'], 'target_guarantee_id' => $ids['b'], 'effective_date' => '2026-10-01', 'note' => 'Essai rétroactif'], cfUser('admin@equipe.test')))
            ->toThrow(AccountingRefusal::class, 'passée');
        expect(fn () => $svc->request(['source_guarantee_id' => $ids['a'], 'target_guarantee_id' => $ids['a'], 'effective_date' => '2026-11-01', 'note' => 'Même formule'], cfUser('admin@equipe.test')))
            ->toThrow(AccountingRefusal::class, 'différente');

        $mig = $svc->request(['source_guarantee_id' => $ids['a'], 'target_guarantee_id' => $ids['b'], 'effective_date' => '2026-11-01', 'keep_seniority' => true,
            'member_ids' => array_values($ids['m']), 'note' => 'Fermeture de la formule Essentielle'], cfUser('admin@equipe.test'));
        expect($mig->reference)->toBe('MIG-2026-001')->and($mig->status)->toBe('requested');
        // « 4 yeux » : le demandeur ne décide pas ; un refus doit être motivé.
        expect(fn () => $svc->decide($mig, true, null, cfUser('admin@equipe.test')))->toThrow(AccountingRefusal::class, 'autre personne');
        expect(fn () => $svc->decide($mig, false, '', cfUser('ctrl@equipe.test')))->toThrow(AccountingRefusal::class, 'motif');
        $mig = $svc->decide($mig, true, 'Conforme à la décision de l’AG', cfUser('ctrl@equipe.test'));
        expect($mig->status)->toBe('approved');   // date d'effet pas encore atteinte : rien n'est appliqué
        expect(fn () => $svc->apply($mig))->toThrow(AccountingRefusal::class, 'pas encore atteinte');
        expect(Subscription::count())->toBe(3);

        return $mig;
    });

    // Le jour J : la tâche de nuit applique la demande.
    $this->travelTo(Carbon::parse('2026-11-01 01:00:00'));
    $this->mutual->run(fn () => expect(app(FormulaMigrationService::class)->applyDue())->toBe(1));
    $this->mutual->run(function () use ($ids, $mig) {
        $mig = $mig->fresh();
        expect($mig->status)->toBe('applied')->and($mig->result['migrated'])->toHaveCount(2)->and($mig->result['skipped'][0]['code'])->toBe('MUT-3');

        $m1 = Member::find($ids['m']['MUT-1']);
        $old = Subscription::where('member_id', $m1->id)->orderBy('id')->first();
        $new = Subscription::where('member_id', $m1->id)->latest('id')->first();
        expect($old->status->value)->toBe('validated')->and($old->ends_on->toDateString())->toBe('2026-10-31');
        expect($new->effective_date->toDateString())->toBe('2026-11-01')->and($new->contribution_minor)->toBe(5000)
            ->and($new->migrated_from_id)->toBe($old->id)->and($new->waiting_from->toDateString())->toBe('2026-01-01')->and($new->validated_by)->toBe(cfUser('ctrl@equipe.test')->id);
        // Échéances : celles de l'ancienne formule après la date d'effet sont retirées ; la nouvelle formule appelle les siennes.
        expect(ContributionSchedule::where('subscription_id', $old->id)->pluck('period')->all())->toBe(['2026-10']);
        expect(ContributionSchedule::where('subscription_id', $new->id)->orderBy('due_date')->first())->due_minor->toBe(5000);

        // Aucune rétroactivité : soins du 20/10 → ancienne formule (80 %) ; soins du 02/11 → nouvelle (60 %), sans nouvelle carence.
        $engine = app(CoverageEngine::class);
        $before = $engine->evaluate($m1, null, $ids['act'], 10000, '2026-10-20');
        $after = $engine->evaluate($m1, null, $ids['act'], 10000, '2026-11-02');
        expect($before['ok'])->toBeTrue()->and($before['rate'])->toBe(80.0)->and($before['version']->guarantee_id)->toBe($ids['a']);
        expect($after['ok'])->toBeTrue()->and($after['rate'])->toBe(60.0)->and($after['version']->guarantee_id)->toBe($ids['b']);

        // MUT-3 écarté : il reste sur l'ancienne formule, intact.
        expect(Subscription::where('member_id', $ids['m']['MUT-3'])->count())->toBe(1)
            ->and(Subscription::where('member_id', $ids['m']['MUT-3'])->first()->ends_on)->toBeNull();
        // Une seule application, même si on recommence.
        expect(fn () => app(FormulaMigrationService::class)->apply($mig))->toThrow(AccountingRefusal::class);
        // Jamais d'échéance de l'ancienne souscription après sa fin, même après la tâche de nuit
        // (elle peut rattraper des mois manquants AVANT la fin, absents de ce jeu de test).
        app(\App\Services\ScheduleGenerationService::class)->refreshAll();
        expect(ContributionSchedule::where('subscription_id', $old->id)->whereDate('due_date', '>', '2026-10-31')->count())->toBe(0);
    });
});

it('se fait par les écrans : aperçu, demande, validation par le contrôleur, application immédiate le jour même', function () {
    $h = $this->host;
    $ids = $this->ids;
    asAdmin()->get($h . "/formules/changements/nouveau?source={$ids['a']}&target={$ids['b']}&date=2026-10-27")->assertOk()
        ->assertSee('Adhérents qui peuvent changer (2)')->assertSee('Adhérents écartés (1)')->assertSee('MUT-3');
    asAdmin()->post($h . '/formules/changements', ['source_guarantee_id' => $ids['a'], 'target_guarantee_id' => $ids['b'], 'effective_date' => '2026-10-27',
        'member_ids' => [$ids['m']['MUT-2']], 'note' => 'Passage volontaire à la formule Famille'])->assertRedirect();
    $mig = $this->mutual->run(fn () => FormulaMigration::latest('id')->first());
    expect($mig->keep_seniority)->toBeFalse()->and($mig->member_ids)->toBe([$ids['m']['MUT-2']]);

    // Droits : l'auditeur consulte, le trésorier n'a pas accès, le demandeur ne peut pas valider.
    as_('audit@equipe.test')->get($h . "/formules/changements/{$mig->id}")->assertOk()->assertSee($mig->reference);
    as_('tresor@equipe.test')->get($h . '/formules/changements')->assertForbidden();
    asAdmin()->get($h . "/formules/changements/{$mig->id}")->assertSee('une autre personne', false)->assertDontSee('Valider le changement');
    asAdmin()->post($h . "/formules/changements/{$mig->id}/valider")->assertSessionHas('error');

    as_('ctrl@equipe.test')->post($h . "/formules/changements/{$mig->id}/valider", ['note' => 'OK'])->assertSessionHas('success', fn ($m) => str_contains($m, 'appliqué'));
    $this->mutual->run(function () use ($ids, $mig) {
        expect($mig->fresh()->status)->toBe('applied')->and($mig->fresh()->result['migrated'])->toHaveCount(1);
        // Seul l'adhérent choisi a changé ; sans ancienneté conservée, la carence de 90 jours de la nouvelle formule s'applique.
        expect(Subscription::where('member_id', $ids['m']['MUT-1'])->count())->toBe(1);
        $m2 = Member::find($ids['m']['MUT-2']);
        $r = app(CoverageEngine::class)->evaluate($m2, null, $ids['act'], 10000, '2026-10-28');
        expect($r['ok'])->toBeFalse()->and($r['status'])->toBe('waiting');
    });
    asAdmin()->get($h . "/formules/changements/{$mig->id}")->assertSee('Adhérents passés à la nouvelle formule (1)');
});
