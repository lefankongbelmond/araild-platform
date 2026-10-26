<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Central\Currency;
use App\Models\Tenant\ContributionPayment;
use App\Models\Tenant\ContributionSchedule;
use App\Services\ReceiptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ContributionController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->get('status', 'overdue');

        $schedules = ContributionSchedule::with('subscription.member')
            ->when(in_array($status, ['to_pay', 'overdue', 'paid']), fn ($q) => $q->where('status', $status))
            ->orderBy('due_date')
            ->paginate(25)
            ->withQueryString();

        return view('tenant.contributions.index', [
            'schedules' => $schedules,
            'status'    => $status,
            'currency'  => $this->currency(),
            'arrears'   => ContributionSchedule::where('status', 'overdue')->count(),
        ]);
    }

    /** Record a cash / group payment (treasurer) — Mobile Money goes through Module 8. */
    public function recordPayment(Request $request, ContributionSchedule $schedule, ReceiptService $receipts)
    {
        abort_unless($request->user()->can('subscription.validate') || $request->user()->hasRole('treasurer_accountant'), 403);
        abort_if($schedule->status === 'paid', 422);

        $request->validate(['mode' => ['required', 'in:cash,group']]);

        $payment = $schedule->payments()->create([
            'amount_minor' => $schedule->due_minor,
            'paid_on'      => now()->toDateString(),
            'mode'         => $request->get('mode'),
        ]);

        $schedule->update(['status' => 'paid']);
        $receipts->generate($payment);

        return back()->with('success', __('mxconnect.contribution.recorded'));
    }

    public function receipt(ContributionPayment $payment)
    {
        abort_unless($payment->receipt_path && Storage::disk('local')->exists($payment->receipt_path), 404);
        return response(Storage::disk('local')->get($payment->receipt_path))
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    private function currency(): Currency
    {
        return Currency::on('central')->find(tenant('currency_id'))
            ?? Currency::on('central')->where('code', 'XAF')->firstOrFail();
    }
}
