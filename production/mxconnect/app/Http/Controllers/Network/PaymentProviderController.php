<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentProviderRequest;
use App\Models\Central\PaymentProvider;

class PaymentProviderController extends Controller
{
    public function index()
    {
        $this->authorize('view-config');
        return view('network.config.providers', ['providers' => PaymentProvider::orderBy('name')->get()]);
    }

    public function store(PaymentProviderRequest $request)
    {
        PaymentProvider::create($request->validated() + ['active' => $request->boolean('active', true)]);
        return back()->with('success', __('mxconnect.config.saved'));
    }

    public function update(PaymentProviderRequest $request, PaymentProvider $provider)
    {
        $provider->update($request->validated());
        return back()->with('success', __('mxconnect.config.saved'));
    }

    public function toggle(PaymentProvider $provider)
    {
        $this->authorize('manage-config');
        $provider->update(['active' => ! $provider->active]);
        return back()->with('success', __('mxconnect.config.saved'));
    }
}
