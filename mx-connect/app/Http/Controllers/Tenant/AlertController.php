<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Alert;
use Illuminate\Http\Request;

/** Anti-fraud alert queue for the fraud officer / controller. */
class AlertController extends Controller
{
    public function index(Request $request)
    {
        $level = $request->get('level');

        return view('tenant.alerts.index', [
            'alerts' => Alert::open()
                ->when(in_array($level, ['normal', 'watch', 'critical']), fn ($q) => $q->where('level', $level))
                ->orderByRaw("FIELD(level, 'critical', 'watch', 'normal')")
                ->orderByDesc('created_at')
                ->paginate(30)->withQueryString(),
            'level'  => $level,
            'counts' => [
                'critical' => Alert::open()->where('level', 'critical')->count(),
                'watch'    => Alert::open()->where('level', 'watch')->count(),
            ],
        ]);
    }

    public function clear(Request $request, Alert $alert)
    {
        abort_unless($request->user()->hasAnyRole(['controller_validator', 'mutual_admin', 'security_admin']), 403);
        $alert->update(['status' => 'cleared', 'cleared_by' => $request->user()->id]);
        return back()->with('success', __('mxconnect.alert.cleared'));
    }
}
