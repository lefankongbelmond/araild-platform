<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Models\Central\BillingPlan;
use App\Models\Central\Currency;
use Illuminate\Http\Request;

/** Billing plan catalogue (Phase 4 SaaS billing). Super-admin only. */
class BillingPlanController extends Controller
{
    public function index()
    {
        $this->authorize('manage-config');
        return view('network.billing.plans', [
            'plans'      => BillingPlan::with('currency')->orderBy('price_minor')->get(),
            'currencies' => Currency::on('central')->where('active', true)->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('manage-config');
        $data = $request->validate([
            'code'        => ['required', 'string', 'max:40', 'unique:central.billing_plans,code'],
            'name'        => ['required', 'string', 'max:120'],
            'price'       => ['required', 'numeric', 'min:0'],
            'currency_id' => ['required', 'integer'],
            'interval'    => ['required', 'in:monthly,annual'],
            'max_members' => ['nullable', 'integer', 'min:0'],
        ]);

        $currency = Currency::on('central')->findOrFail($data['currency_id']);

        BillingPlan::create([
            'code'        => $data['code'],
            'name'        => $data['name'],
            'price_minor' => $currency->toMinor((float) $data['price']),
            'currency_id' => $data['currency_id'],
            'interval'    => $data['interval'],
            'max_members' => $data['max_members'] ?? null,
            'active'      => true,
        ]);

        return back()->with('success', __('mxconnect.billing.plan_created'));
    }

    public function toggle(BillingPlan $plan)
    {
        $this->authorize('manage-config');
        $plan->update(['active' => ! $plan->active]);
        return back()->with('success', __('mxconnect.billing.plan_toggled'));
    }
}
