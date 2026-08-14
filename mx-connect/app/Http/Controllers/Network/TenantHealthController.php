<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Services\TenantHealthService;

/** Operator tenant-health dashboard (Phase 3). Super-admin only. */
class TenantHealthController extends Controller
{
    public function index(TenantHealthService $service)
    {
        $this->authorize('manage-config');

        $rows = $service->snapshot();

        return view('network.health.index', [
            'rows'        => $rows,
            'unreachable' => collect($rows)->where('reachable', false)->count(),
        ]);
    }
}
