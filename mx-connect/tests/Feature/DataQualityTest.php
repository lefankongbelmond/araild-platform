<?php

use App\Models\Tenant\ContributionSchedule;
use App\Models\Tenant\Member;
use App\Models\Tenant\Subscription;
use App\Services\DataQualityService;

/** Module 12 — data-quality scoring. */

it('scores a perfectly complete dataset at 100', function () {
    $m = Member::create([
        'member_code' => 'ADH-26-AAAAA', 'first_name' => 'A', 'last_name' => 'B', 'birth_date' => '1990-01-01',
        'sex' => 'M', 'phone' => '690000001', 'id_document' => 'CNI123', 'antenna_id' => 1,
        'status' => 'active', 'joined_at' => now()->toDateString(),
    ]);
    $sub = Subscription::create(['member_id' => $m->id, 'status' => 'validated']);
    ContributionSchedule::create(['subscription_id' => $sub->id, 'period' => 'P1', 'due_date' => now()->toDateString(), 'due_minor' => 1000, 'status' => 'pending']);

    $r = app(DataQualityService::class)->evaluate();

    expect($r['score'])->toBe(100);
})->group('tenant');

it('penalises missing fields proportionally to weight', function () {
    // One member missing phone (weight 2) and id_document (weight 1); everything else fine.
    $m = Member::create([
        'member_code' => 'ADH-26-BBBBB', 'first_name' => 'A', 'last_name' => 'B', 'birth_date' => '1990-01-01',
        'sex' => 'M', 'antenna_id' => 1, 'status' => 'active', 'joined_at' => now()->toDateString(),
    ]);
    $sub = Subscription::create(['member_id' => $m->id, 'status' => 'validated']);
    ContributionSchedule::create(['subscription_id' => $sub->id, 'period' => 'P1', 'due_date' => now()->toDateString(), 'due_minor' => 1000, 'status' => 'pending']);

    $r = app(DataQualityService::class)->evaluate();

    // Failing weight = phone(2)+id(1)=3 of total weight 11 => score = round((11-3)/11*100)=73.
    expect($r['score'])->toBe(73)
        ->and(collect($r['checks'])->firstWhere('key', 'member_phone')['rate'])->toBe(0.0);
})->group('tenant');
