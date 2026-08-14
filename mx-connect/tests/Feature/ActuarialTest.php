<?php

use App\Models\Tenant\CareClaim;
use App\Models\Tenant\ContributionPayment;
use App\Models\Tenant\ContributionSchedule;
use App\Models\Tenant\Member;
use App\Models\Tenant\Prestation;
use App\Models\Tenant\Subscription;
use App\Models\Tenant\TreasuryMovement;
use App\Services\ActuarialService;

/** Module 12 — actuarial & solvency computations. */

beforeEach(fn () => $this->act = app(ActuarialService::class));

function seedMember(): Member
{
    return Member::create([
        'member_code' => 'ADH-26-'.strtoupper(\Illuminate\Support\Str::random(5)),
        'first_name' => 'A', 'last_name' => 'B', 'birth_date' => '1990-01-01',
        'sex' => 'M', 'status' => 'active', 'joined_at' => now()->toDateString(),
    ]);
}

function seedPrestation(int $mutualPart, string $careDate, string $status = 'validated'): void
{
    $m = seedMember();
    $claim = CareClaim::create(['member_id' => $m->id, 'opened_on' => $careDate, 'reason' => 'x', 'status' => 'open']);
    Prestation::create([
        'care_claim_id' => $claim->id, 'act_type_id' => 1, 'provider_id' => 1, 'guarantee_version_id' => 1,
        'care_date' => $careDate, 'total_minor' => $mutualPart * 2,
        'mutual_part_minor' => $mutualPart, 'beneficiary_part_minor' => $mutualPart, 'status' => $status,
    ]);
}

it('computes the loss ratio as claims over premiums', function () {
    $year = (int) now()->year;
    $sub = Subscription::create(['member_id' => seedMember()->id, 'status' => 'validated']);
    $sch = ContributionSchedule::create(['subscription_id' => $sub->id, 'period' => 'P1', 'due_date' => now()->toDateString(), 'due_minor' => 100000, 'status' => 'paid']);
    ContributionPayment::create(['schedule_id' => $sch->id, 'amount_minor' => 100000, 'paid_on' => now()->toDateString(), 'mode' => 'cash']);

    seedPrestation(60000, now()->toDateString());

    expect($this->act->collectedContributions($year))->toBe(100000)
        ->and($this->act->incurredClaims($year))->toBe(60000)
        ->and($this->act->lossRatio($year))->toBe(0.6)
        ->and($this->act->lossRatioBand(0.6))->toBe('healthy')
        ->and($this->act->technicalResult($year))->toBe(40000);
})->group('tenant');

it('only counts validated prestations as incurred claims', function () {
    $year = (int) now()->year;
    seedPrestation(50000, now()->toDateString(), 'validated');
    seedPrestation(99000, now()->toDateString(), 'captured');   // not yet validated
    seedPrestation(88000, now()->toDateString(), 'rejected');   // rejected

    expect($this->act->incurredClaims($year))->toBe(50000);
})->group('tenant');

it('bands the loss ratio by config thresholds', function () {
    expect($this->act->lossRatioBand(0.5))->toBe('healthy')
        ->and($this->act->lossRatioBand(0.9))->toBe('watch')
        ->and($this->act->lossRatioBand(1.2))->toBe('critical')
        ->and($this->act->lossRatioBand(null))->toBe('unknown');
})->group('tenant');

it('computes the solvency ratio as reserves over required reserve', function () {
    // Reserves: 300000 in, 60000 out => 240000 net.
    TreasuryMovement::create(['direction' => 'inflow', 'source' => 'contribution', 'amount_minor' => 300000, 'mode' => 'cash', 'moved_on' => now()->toDateString()]);
    TreasuryMovement::create(['direction' => 'outflow', 'source' => 'reimbursement', 'amount_minor' => 60000, 'mode' => 'cash', 'moved_on' => now()->toDateString()]);
    // Trailing claims 240000 over 12 months => avg 20000/mo; required = 3 * 20000 = 60000.
    seedPrestation(240000, now()->subMonths(1)->toDateString());

    expect($this->act->reserves())->toBe(240000)
        ->and($this->act->requiredReserve())->toBe(60000)
        ->and($this->act->solvencyRatio())->toBe(4.0);
})->group('tenant');
