<?php

use App\Services\RiskScoringService;

/** Module 13 — risk scoring & banding. */

beforeEach(fn () => $this->svc = app(RiskScoringService::class));

it('computes score as likelihood times impact', function () {
    expect($this->svc->score(4, 5))->toBe(20)
        ->and($this->svc->score(1, 1))->toBe(1);
});

it('bands the score by the configured matrix', function () {
    // bands: low<=4, medium<=9, high<=15, else critical
    expect($this->svc->band(3))->toBe('low')
        ->and($this->svc->band(4))->toBe('low')
        ->and($this->svc->band(6))->toBe('medium')
        ->and($this->svc->band(9))->toBe('medium')
        ->and($this->svc->band(12))->toBe('high')
        ->and($this->svc->band(15))->toBe('high')
        ->and($this->svc->band(20))->toBe('critical')
        ->and($this->svc->band(25))->toBe('critical');
});
