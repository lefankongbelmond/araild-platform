<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\DataQualityService;
use App\Support\TenantCache;
use Illuminate\Http\Request;

/** Data-quality score dashboard (CDC Phase 2, §26). */
class DataQualityController extends Controller
{
    public function index(Request $request, DataQualityService $service)
    {
        abort_unless($request->user()->hasAnyRole(['mutual_admin', 'controller_validator', 'security_admin']), 403);

        // Several cross-table counts — cache briefly per tenant; slight staleness is fine.
        $data = TenantCache::remember('data_quality', 300, fn () => $service->evaluate());

        return view('tenant.quality.index', $data);
    }
}
