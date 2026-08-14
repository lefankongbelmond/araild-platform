<?php

use App\Support\TenantCache;
use Illuminate\Support\Facades\Cache;

/** Module 15 — tenant-aware cache key scoping. */

it('prefixes keys with the central scope when no tenant is bound', function () {
    expect(TenantCache::key('solvency:2026'))->toBe('t:central:solvency:2026');
});

it('remembers and forgets under the scoped key', function () {
    $calls = 0;
    $make = fn () => TenantCache::remember('x', 60, function () use (&$calls) { $calls++; return 'v'; });

    expect($make())->toBe('v')
        ->and($make())->toBe('v')     // served from cache, callback not re-run
        ->and($calls)->toBe(1)
        ->and(Cache::has(TenantCache::key('x')))->toBeTrue();

    TenantCache::forget('x');
    expect(Cache::has(TenantCache::key('x')))->toBeFalse();
});
