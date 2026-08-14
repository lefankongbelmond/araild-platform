<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Models\Central\Currency;
use App\Services\ConsolidationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Network consolidation dashboard (CDC Phase 4, §31). Aggregates every mutual's
 * membership and financials for the operator and funders. The snapshot iterates
 * all tenant databases, so it is cached briefly and can be refreshed on demand.
 */
class ConsolidationController extends Controller
{
    public function index(Request $request, ConsolidationService $service)
    {
        $this->authorize('view-config');

        $year = (int) $request->get('year', now()->year);

        if ($request->boolean('refresh')) {
            Cache::forget("network:consolidation:{$year}");
        }

        $data = Cache::remember("network:consolidation:{$year}", 600, fn () => $service->snapshot($year));

        return view('network.consolidation.index', [
            'year'       => $year,
            'rows'       => $data['rows'],
            'totals'     => $data['totals'],
            'currencies' => Currency::on('central')->get()->keyBy('code'),
        ]);
    }
}
