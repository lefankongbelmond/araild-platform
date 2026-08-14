<?php

use App\Models\Central\DrTest;
use App\Services\DrAssessmentService;

/** Module 14 — DR drill objective evaluation and verdict. */

beforeEach(fn () => $this->svc = app(DrAssessmentService::class));

function makeDrTest(array $overrides = []): DrTest
{
    return DrTest::create(array_merge([
        'ref' => 'DRT-26-'.strtoupper(\Illuminate\Support\Str::random(4)),
        'type' => 'backup_restore', 'scope' => 'central',
        'performed_on' => now()->toDateString(), 'outcome' => 'success',
    ], $overrides));
}

it('marks an objective met only when actual <= target', function () {
    expect($this->svc->objectiveMet(240, 200))->toBeTrue()
        ->and($this->svc->objectiveMet(240, 240))->toBeTrue()
        ->and($this->svc->objectiveMet(240, 300))->toBeFalse()
        ->and($this->svc->objectiveMet(240, null))->toBeFalse()
        ->and($this->svc->objectiveMet(null, 100))->toBeFalse();
});

it('returns a clean verdict when success and objectives met', function () {
    $t = makeDrTest([
        'rto_target_minutes' => 240, 'rto_actual_minutes' => 180,
        'rpo_target_minutes' => 60,  'rpo_actual_minutes' => 30, 'outcome' => 'success',
    ]);
    expect($this->svc->verdict($t))->toBe('clean');
})->group('central');

it('degrades the verdict when an objective is missed even if marked success', function () {
    $t = makeDrTest([
        'rto_target_minutes' => 240, 'rto_actual_minutes' => 400,   // blew RTO
        'rpo_target_minutes' => 60,  'rpo_actual_minutes' => 30, 'outcome' => 'success',
    ]);
    expect($this->svc->verdict($t))->toBe('degraded');
})->group('central');

it('flags incomplete when a target has no measured actual', function () {
    $t = makeDrTest(['rto_target_minutes' => 240, 'rto_actual_minutes' => null, 'outcome' => 'success']);
    expect($this->svc->verdict($t))->toBe('incomplete');
})->group('central');

it('returns failed when the operator marked it failed', function () {
    $t = makeDrTest(['outcome' => 'failed']);
    expect($this->svc->verdict($t))->toBe('failed');
})->group('central');
