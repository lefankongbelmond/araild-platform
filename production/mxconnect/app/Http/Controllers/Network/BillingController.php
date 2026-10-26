<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Models\Central\BillingInvoice;
use App\Models\Central\BillingPlan;
use App\Models\Central\BillingSubscription;
use App\Models\Central\Mutual;
use App\Services\BillingService;
use Illuminate\Http\Request;

/** SaaS billing overview & actions (Phase 4). Super-admin only. */
class BillingController extends Controller
{
    public function __construct(private BillingService $billing) {}

    public function index()
    {
        $this->authorize('manage-config');

        return view('network.billing.index', [
            'subscriptions' => BillingSubscription::with(['mutual', 'plan.currency'])->get()->sortBy(fn ($s) => $s->mutual?->name)->values(),
            'invoices'      => BillingInvoice::with(['mutual', 'currency'])->orderByDesc('issued_on')->orderByDesc('id')->limit(50)->get(),
            'unsubscribed'  => Mutual::where('status', 'approved')
                ->whereNotIn('id', BillingSubscription::select('mutual_id'))->orderBy('name')->get(),
            'plans'         => BillingPlan::where('active', true)->orderBy('price_minor')->get(),
        ]);
    }

    public function subscribe(Request $request)
    {
        $this->authorize('manage-config');
        $data = $request->validate([
            'mutual_id'       => ['required', 'integer', 'exists:central.mutuals,id'],
            'billing_plan_id' => ['required', 'integer', 'exists:central.billing_plans,id'],
        ]);

        $this->billing->subscribe(
            Mutual::findOrFail($data['mutual_id']),
            BillingPlan::findOrFail($data['billing_plan_id']),
        );

        return back()->with('success', __('mxconnect.billing.subscribed'));
    }

    public function invoice(BillingSubscription $subscription)
    {
        $this->authorize('manage-config');
        $invoice = $this->billing->generateInvoice($subscription);

        return back()->with('success', $invoice
            ? __('mxconnect.billing.invoice_generated', ['n' => $invoice->number])
            : __('mxconnect.billing.invoice_exists'));
    }

    public function markPaid(BillingInvoice $invoice)
    {
        $this->authorize('manage-config');
        $this->billing->markPaid($invoice);

        return back()->with('success', __('mxconnect.billing.marked_paid'));
    }
}
