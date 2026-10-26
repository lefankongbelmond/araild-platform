<?php

namespace App\Http\Controllers;

use App\Models\Central\Country;
use App\Models\Central\Mutual;
use Illuminate\Http\Request;

/**
 * Public network directory (CDC Phase 4 seed). Lists approved mutuals with basic
 * public info so prospective members can find and reach them. No auth.
 */
class PublicDirectoryController extends Controller
{
    public function index(Request $request)
    {
        $countryId = $request->get('country');

        $mutuals = Mutual::where('status', 'approved')
            ->when($countryId, fn ($q) => $q->where('country_id', $countryId))
            ->with('country')
            ->orderBy('name')->paginate(24)->withQueryString();

        return view('public.directory', [
            'mutuals'   => $mutuals,
            'countries' => Country::where('active', true)->orderBy('name')->get(),
            'countryId' => $countryId,
        ]);
    }
}
