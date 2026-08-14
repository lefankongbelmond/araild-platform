<?php

use App\Models\Tenant\Member;
use App\Services\DuplicateDetectionService;

/** Module 5 — duplicate detection: strong blocks, weak warns then allows on confirm. */

it('blocks on a strong match (same phone)', function () {
    Member::factory()->create(['phone' => '+237690000001']);

    $r = app(DuplicateDetectionService::class)->check(
        'Jean', 'Kamdem', '1990-01-01', '+237690000001', null
    );

    expect($r['block'])->toBeTrue()
        ->and($r['strong'])->toHaveCount(1);
})->group('tenant');

it('blocks on a strong match (same encrypted id document)', function () {
    Member::factory()->create(['id_document' => 'CNI-123456']);

    $r = app(DuplicateDetectionService::class)->check(
        'Autre', 'Personne', '1985-05-05', null, 'cni 123456'   // normalised match
    );

    expect($r['block'])->toBeTrue();
})->group('tenant');

it('warns on a weak match (same name + birth date) then allows with confirmation', function () {
    Member::factory()->create([
        'first_name' => 'Awa', 'last_name' => 'Njoya', 'birth_date' => '1992-03-10',
        'phone' => '+237690000010',
    ]);

    // Different phone => not strong, but same name + dob => weak
    $r = app(DuplicateDetectionService::class)->check(
        'Awa', 'Njoya', '1992-03-10', '+237690000999', null
    );

    expect($r['block'])->toBeFalse()
        ->and($r['warn'])->toBeTrue()
        ->and($r['weak'])->toHaveCount(1);
})->group('tenant');

it('does not flag a genuinely new member', function () {
    Member::factory()->create(['first_name' => 'Awa', 'last_name' => 'Njoya', 'birth_date' => '1992-03-10']);

    $r = app(DuplicateDetectionService::class)->check(
        'Paul', 'Biya', '1970-07-07', '+237690001234', 'CNI-999'
    );

    expect($r['block'])->toBeFalse()
        ->and($r['warn'])->toBeFalse();
})->group('tenant');

it('ignores the member itself when editing', function () {
    $m = Member::factory()->create(['phone' => '+237690000055']);

    $r = app(DuplicateDetectionService::class)->check(
        $m->first_name, $m->last_name, $m->birth_date->toDateString(),
        '+237690000055', null, ignoreId: $m->id
    );

    expect($r['block'])->toBeFalse();
})->group('tenant');
