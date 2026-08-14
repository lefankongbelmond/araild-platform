<?php

use App\Notifications\ContributionReminder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\RateLimiter;

/** Module 15 — rate limiters registered and reminder queued. */

it('registers the named rate limiters', function () {
    expect(RateLimiter::limiter('member-api'))->not->toBeNull()
        ->and(RateLimiter::limiter('webhooks'))->not->toBeNull()
        ->and(RateLimiter::limiter('login'))->not->toBeNull();
});

it('makes the contribution reminder a queued notification', function () {
    expect(is_subclass_of(ContributionReminder::class, ShouldQueue::class))->toBeTrue();
});
