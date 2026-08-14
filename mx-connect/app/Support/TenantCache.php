<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Tenant-aware cache helper (Phase 3). Every key is namespaced by the current
 * tenant so two mutuals never read each other's cached read-models, and so a
 * single mutual's entries can be targeted for invalidation. Falls back to a
 * 'central' namespace when no tenant is bound (network-side callers).
 */
class TenantCache
{
    public static function key(string $suffix): string
    {
        $scope = function_exists('tenant') && tenant() ? tenant('id') : 'central';
        return "t:{$scope}:{$suffix}";
    }

    /** Remember a value under a tenant-scoped key. */
    public static function remember(string $suffix, int $ttlSeconds, Closure $callback): mixed
    {
        return Cache::remember(self::key($suffix), $ttlSeconds, $callback);
    }

    public static function forget(string $suffix): void
    {
        Cache::forget(self::key($suffix));
    }

    /** Forget several suffixes at once (e.g. after a write invalidates read-models). */
    public static function forgetMany(array $suffixes): void
    {
        foreach ($suffixes as $suffix) {
            self::forget($suffix);
        }
    }
}
