<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Liveness/readiness probe (Phase 3). Returns a compact JSON status for the app,
 * the central database and the cache store. Intended for load balancers and
 * uptime monitors — it deliberately exposes no sensitive detail.
 */
class HealthController extends Controller
{
    public function show()
    {
        $checks = [
            'app'   => true,
            'db'    => $this->ok(fn () => DB::connection('central')->select('select 1')),
            'cache' => $this->ok(function () {
                Cache::put('health:ping', '1', 5);
                return Cache::get('health:ping') === '1';
            }),
        ];

        $healthy = ! in_array(false, $checks, true);

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
            'time'   => now()->toIso8601String(),
        ], $healthy ? 200 : 503);
    }

    private function ok(callable $probe): bool
    {
        try {
            $probe();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
