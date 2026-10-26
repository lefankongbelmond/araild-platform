<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Central\Currency;
use App\Services\ActuarialService;
use Illuminate\Http\Request;

/**
 * Actuarial & solvency dashboard (CDC Phase 2, §21). Read-only analytics over the
 * mutual's own financial data. Restricted to management/oversight roles.
 */
class SolvencyController extends Controller
{
    public function dashboard(Request $request, ActuarialService $act)
    {
        abort_unless($request->user()->hasAnyRole(['mutual_admin', 'treasurer_accountant', 'controller_validator']), 403);

        $year = (int) $request->get('year', now()->year);
        $ratio = $act->lossRatio($year);
        $solvency = $act->solvencyRatio();
        $currency = $this->currency();

        return view('tenant.solvency.dashboard', [
            'year'          => $year,
            'premiums'      => $act->collectedContributions($year),
            'claims'        => $act->incurredClaims($year),
            'lossRatio'     => $ratio,
            'lossBand'      => $act->lossRatioBand($ratio),
            'technical'     => $act->technicalResult($year),
            'reserves'      => $act->reserves(),
            'required'      => $act->requiredReserve(),
            'solvency'      => $solvency,
            'solvencyFloor' => (float) config('mxconnect.actuarial.solvency_floor'),
            'series'        => $act->monthlySeries($year),
            'currency'      => $currency,
        ]);
    }

    private function currency(): Currency
    {
        return Currency::on('central')->find(tenant('currency_id'))
            ?? Currency::on('central')->where('code', 'XAF')->firstOrFail();
    }
}
