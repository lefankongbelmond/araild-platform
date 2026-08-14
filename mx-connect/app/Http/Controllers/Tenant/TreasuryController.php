<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Central\Currency;
use App\Models\Tenant\AccountingDay;
use App\Models\Tenant\TreasuryMovement;
use App\Services\AccountingService;
use App\Services\DailyCloseService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TreasuryController extends Controller
{
    public function dashboard(DailyCloseService $close)
    {
        $inflow  = (int) TreasuryMovement::where('direction', 'inflow')->sum('amount_minor');
        $outflow = (int) TreasuryMovement::where('direction', 'outflow')->sum('amount_minor');

        // Balance by treasury mode.
        $byMode = [];
        foreach (['cash', 'bank', 'mobile_money'] as $mode) {
            $in  = (int) TreasuryMovement::where('mode', $mode)->where('direction', 'inflow')->sum('amount_minor');
            $out = (int) TreasuryMovement::where('mode', $mode)->where('direction', 'outflow')->sum('amount_minor');
            $byMode[$mode] = $in - $out;
        }

        // 6-month inflow/outflow series for the chart.
        $series = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $series[] = [
                'label'   => $month->format('Y-m'),
                'inflow'  => (int) TreasuryMovement::where('direction', 'inflow')->whereYear('moved_on', $month->year)->whereMonth('moved_on', $month->month)->sum('amount_minor'),
                'outflow' => (int) TreasuryMovement::where('direction', 'outflow')->whereYear('moved_on', $month->year)->whereMonth('moved_on', $month->month)->sum('amount_minor'),
            ];
        }

        return view('tenant.treasury.dashboard', [
            'balance'      => $inflow - $outflow,
            'inflow'       => $inflow,
            'outflow'      => $outflow,
            'byMode'       => $byMode,
            'series'       => $series,
            'theoretical'  => $close->theoreticalCash(now()->toDateString()),
            'currency'     => $this->currency(),
            'unposted'     => TreasuryMovement::whereNull('accounting_day_id')->count(),
        ]);
    }

    public function movements(Request $request)
    {
        $direction = $request->get('direction');

        return view('tenant.treasury.movements', [
            'movements' => TreasuryMovement::query()
                ->when(in_array($direction, ['inflow', 'outflow']), fn ($q) => $q->where('direction', $direction))
                ->orderByDesc('moved_on')->orderByDesc('id')->paginate(30)->withQueryString(),
            'direction' => $direction,
            'currency'  => $this->currency(),
        ]);
    }

    /** Manual treasury adjustment (with supporting reference). Blocked on a closed day. */
    public function storeMovement(Request $request, AccountingService $accounting)
    {
        abort_unless($request->user()->hasRole('treasurer_accountant'), 403);

        $data = $request->validate([
            'direction'    => ['required', 'in:inflow,outflow'],
            'source'       => ['required', 'in:membership_fee,contribution,provider_invoice,reimbursement,other'],
            'mode'         => ['required', 'in:cash,bank,mobile_money'],
            'amount'       => ['required', 'numeric', 'min:0'],
            'moved_on'     => ['required', 'date'],
            'reference'    => ['nullable', 'string', 'max:60'],
        ]);

        // Guard: no movement can be dated onto a closed accounting day.
        $day = AccountingDay::whereDate('day', $data['moved_on'])->first();
        if ($day && $day->isClosed()) {
            return back()->with('error', __('mxconnect.treasury.day_closed'));
        }

        TreasuryMovement::create([
            'direction'        => $data['direction'],
            'source'           => $data['source'],
            'amount_minor'     => $this->currency()->toMinor((float) $data['amount']),
            'mode'             => $data['mode'],
            'linked_reference' => $data['reference'] ?? null,
            'moved_on'         => $data['moved_on'],
        ]);

        return back()->with('success', __('mxconnect.treasury.recorded'));
    }

    private function currency(): Currency
    {
        return Currency::on('central')->find(tenant('currency_id'))
            ?? Currency::on('central')->where('code', 'XAF')->firstOrFail();
    }
}
