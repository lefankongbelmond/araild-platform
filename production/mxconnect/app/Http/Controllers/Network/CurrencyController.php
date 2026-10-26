<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Http\Requests\CurrencyRequest;
use App\Models\Central\Currency;

class CurrencyController extends Controller
{
    public function index()
    {
        $this->authorize('view-config');
        return view('network.config.currencies', ['currencies' => Currency::orderBy('code')->get()]);
    }

    public function store(CurrencyRequest $request)
    {
        Currency::create($request->validated() + ['active' => $request->boolean('active', true)]);
        return back()->with('success', __('mxconnect.config.saved'));
    }

    public function update(CurrencyRequest $request, Currency $currency)
    {
        $currency->update($request->validated());
        return back()->with('success', __('mxconnect.config.saved'));
    }

    public function toggle(Currency $currency)
    {
        $this->authorize('manage-config');
        $currency->update(['active' => ! $currency->active]);
        return back()->with('success', __('mxconnect.config.saved'));
    }
}
