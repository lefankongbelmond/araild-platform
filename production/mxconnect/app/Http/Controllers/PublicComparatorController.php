<?php

namespace App\Http\Controllers;

use App\Models\Central\Country;
use App\Models\Central\MutualOffer;
use Illuminate\Http\Request;

/**
 * Public offer comparator (CDC Phase 4, §30). Prospective members compare mutual
 * offers side by side. Reads only the denormalised mutual_offers table (fast, no
 * tenant access); filterable by country.
 */
class PublicComparatorController extends Controller
{
    public function index(Request $request)
    {
        $countryId = $request->get('country');

        $offers = MutualOffer::query()
            ->where('published', true)
            ->with(['mutual.country', 'currency'])
            ->whereHas('mutual', fn ($q) => $q->where('status', 'approved')
                ->when($countryId, fn ($qq) => $qq->where('country_id', $countryId)))
            ->get()
            ->sortBy(fn ($o) => $o->mutual->name)
            ->values();

        return view('public.comparator', [
            'offers'    => $offers,
            'countries' => Country::on('central')->where('active', true)->orderBy('name')->get(),
            'countryId' => $countryId,
        ]);
    }
}
