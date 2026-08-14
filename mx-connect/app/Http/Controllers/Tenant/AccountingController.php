<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Central\Currency;
use App\Models\Tenant\Account;
use App\Models\Tenant\AccountingDay;
use App\Models\Tenant\AccountingEntry;
use App\Models\Tenant\TreasuryMovement;
use App\Services\AccountingService;
use App\Services\DailyCloseService;
use Illuminate\Http\Request;

class AccountingController extends Controller
{
    /** SYSCOHADA chart of accounts. */
    public function chart()
    {
        return view('tenant.accounting.chart', [
            'accounts' => Account::orderBy('number')->get()->groupBy('class'),
        ]);
    }

    /** The journal (grand livre / journal). */
    public function journal(Request $request)
    {
        return view('tenant.accounting.journal', [
            'entries'  => AccountingEntry::with(['debitAccount', 'creditAccount', 'accountingDay'])
                ->orderByDesc('id')->paginate(40),
            'currency' => $this->currency(),
        ]);
    }

    /** Post all unposted treasury movements into the journal. */
    public function postTreasury(Request $request, AccountingService $accounting)
    {
        abort_unless($request->user()->hasAnyRole(['treasurer_accountant', 'mutual_admin']), 403);

        $posted = 0; $skipped = 0;
        TreasuryMovement::whereNull('accounting_day_id')->orderBy('moved_on')->each(function (TreasuryMovement $m) use ($accounting, &$posted, &$skipped) {
            try {
                if ($accounting->postFromMovement($m)) { $posted++; } else { $skipped++; }
            } catch (\RuntimeException $e) {
                $skipped++; // e.g. day already closed — left for manual handling
            }
        });

        return back()->with('success', __('mxconnect.accounting.posted', ['n' => $posted, 's' => $skipped]));
    }

    /** Daily cash close screen (arrêté de caisse). */
    public function closeForm(DailyCloseService $close)
    {
        return view('tenant.accounting.close', [
            'theoretical' => $close->theoreticalCash(now()->toDateString()),
            'today'       => now()->toDateString(),
            'recentDays'  => AccountingDay::orderByDesc('day')->limit(10)->get(),
            'currency'    => $this->currency(),
        ]);
    }

    public function close(Request $request, AccountingService $accounting, DailyCloseService $close)
    {
        abort_unless($request->user()->hasRole('treasurer_accountant'), 403);

        $data = $request->validate([
            'day'    => ['required', 'date'],
            'actual' => ['required', 'numeric', 'min:0'],
        ]);

        $day = $accounting->resolveDay($data['day']);
        if ($day->isClosed()) {
            return back()->with('error', __('mxconnect.accounting.already_closed'));
        }

        $discrepancy = $close->close($day, $this->currency()->toMinor((float) $data['actual']));

        $key = $discrepancy === 0 ? 'mxconnect.accounting.closed_ok' : 'mxconnect.accounting.closed_diff';
        return back()->with('success', __($key, ['amount' => $this->currency()->format(abs($discrepancy))]));
    }

    private function currency(): Currency
    {
        return Currency::on('central')->find(tenant('currency_id'))
            ?? Currency::on('central')->where('code', 'XAF')->firstOrFail();
    }
}
