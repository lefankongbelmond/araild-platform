<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Models\Central\Country;
use App\Models\Central\Currency;
use App\Models\Central\Locale;
use App\Models\Central\PaymentProvider;

/** Configuration hub — one place to reach every reference table. */
class ConfigController extends Controller
{
    public function index()
    {
        $this->authorize('view-config');

        return view('network.config.index', [
            'counts' => [
                'currencies' => Currency::count(),
                'locales'    => Locale::count(),
                'countries'  => Country::count(),
                'providers'  => PaymentProvider::count(),
            ],
        ]);
    }
}
