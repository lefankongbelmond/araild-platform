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
        \App\Support\TenantAccess::authorize();

        // Several cross-table counts — cache briefly per tenant; slight staleness is fine.
        $data = TenantCache::remember('data_quality', 300, fn () => $service->evaluate());

        return view('tenant.quality.index', $data);
    }
}
