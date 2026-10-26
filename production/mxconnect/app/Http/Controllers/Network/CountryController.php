<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Http\Requests\CountryRequest;
use App\Models\Central\Country;
use App\Models\Central\Currency;
use App\Models\Central\Locale;
use App\Models\Central\PaymentProvider;
use App\Support\CountryCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CountryController extends Controller
{
    public function index()
    {
        $this->authorize('view-config');
        return view('network.config.countries', [
            'countries'  => Country::with(['defaultCurrency', 'defaultLocale', 'paymentProviders'])->withCount('mutuals')->orderBy('name')->get(),
            'catalog'    => CountryCatalog::REGIONS,
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

    /**
     * Ajout en un clic depuis la liste de référence (2026-11-02). Les devises et langues
     * manquantes sont créées ; un pays déjà présent n'est jamais modifié.
     */
    public function catalog(Request $request)
    {
        $this->authorize('manage-config');
        $data = $request->validate(['iso2' => ['required', 'array', 'min:1'], 'iso2.*' => ['string', 'size:2']],
            ['iso2.required' => 'Cochez au moins un pays.']);

        $added = DB::connection('central')->transaction(function () use ($data) {
            $added = [];
            foreach (array_unique(array_map('strtoupper', $data['iso2'])) as $iso) {
                $ref = CountryCatalog::find($iso);
                if (! $ref || Country::where('iso2', $iso)->exists()) {
                    continue;
                }
                [, $name, $code, $phone, $lang] = $ref;
                [$cName, $symbol, $minor] = CountryCatalog::CURRENCIES[$code];
                $currency = Currency::firstOrCreate(['code' => $code], ['name' => $cName, 'symbol' => $symbol, 'minor_unit' => $minor, 'active' => true]);
                $locale = Locale::firstOrCreate(['code' => $lang], ['name' => CountryCatalog::LOCALES[$lang], 'active' => true]);
                Country::create(['iso2' => $iso, 'name' => $name, 'default_currency_id' => $currency->id,
                    'default_locale_id' => $locale->id, 'phone_prefix' => $phone, 'active' => true]);
                $added[] = $name;
            }

            return $added;
        });

        return back()->with('success', $added
            ? count($added) . ' pays ajouté(s) : ' . implode(', ', $added) . '.'
            : 'Aucun pays ajouté : ils figurent déjà dans la liste.');
    }

    /** Activer / désactiver un pays (un pays inactif n'est plus proposé à la création d'une mutuelle). */
    public function toggle(Country $country)
    {
        $this->authorize('manage-config');
        $country->update(['active' => ! $country->active]);

        return back()->with('success', "{$country->name} : " . ($country->active ? 'activé.' : 'désactivé (les mutuelles existantes ne sont pas touchées).'));
    }
}
