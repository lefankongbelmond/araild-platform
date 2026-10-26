<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Http\Requests\CountryRequest;
use App\Models\Central\Country;
use App\Models\Central\Currency;
use App\Models\Central\Locale;
use App\Models\Central\PaymentProvider;

class CountryController extends Controller
{
    public function index()
    {
        $this->authorize('view-config');
        return view('network.config.countries', [
            'countries'  => Country::with(['defaultCurrency', 'defaultLocale', 'paymentProviders'])->orderBy('name')->get(),
            'currencies' => Currency::where('active', true)->orderBy('code')->get(),
            'locales'    => Locale::where('active', true)->orderBy('name')->get(),
            'providers'  => PaymentProvider::where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(CountryRequest $request)
    {
        $data = $request->validated();
        $providers = $data['providers'] ?? [];
        unset($data['providers']);

        $country = Country::create($data + ['active' => $request->boolean('active', true)]);
        $country->paymentProviders()->sync($providers);

        return back()->with('success', __('mxconnect.config.saved'));
    }

    public function update(CountryRequest $request, Country $country)
    {
        $data = $request->validated();
        $providers = $data['providers'] ?? [];
        unset($data['providers']);

        $country->update($data);
        $country->paymentProviders()->sync($providers);

        return back()->with('success', __('mxconnect.config.saved'));
    }
}
