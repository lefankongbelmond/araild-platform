<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMutualRequest;
use App\Models\Central\Country;
use App\Models\Central\Currency;
use App\Models\Central\Locale;
use App\Models\Central\Mutual;
use App\Services\MutualProvisioningService;
use Illuminate\Http\Request;

class MutualController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Mutual::class);

        $mutuals = Mutual::with(['country', 'currency'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('network.mutuals.index', compact('mutuals'));
    }

    public function create()
    {
        $this->authorize('create', Mutual::class);

        return view('network.mutuals.create', [
            'countries'  => Country::where('active', true)->orderBy('name')->get(),
            'currencies' => Currency::where('active', true)->orderBy('code')->get(),
            'locales'    => Locale::where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreMutualRequest $request, MutualProvisioningService $provisioner)
    {
        // Creating the Mutual fires the tenancy pipeline: create DB -> migrate -> seed.
        $mutual = $provisioner->create($request->validated());

        return redirect()
            ->route('network.mutuals.index')
            ->with('success', __('mxconnect.mutual.created', ['name' => $mutual->name]));
    }

    public function approve(Request $request, Mutual $mutual, MutualProvisioningService $provisioner)
    {
        $this->authorize('approve', $mutual);

        $provisioner->approve($mutual, $request->user('network')->id);

        return back()->with('success', __('mxconnect.mutual.approved', ['name' => $mutual->name]));
    }

    public function suspend(Request $request, Mutual $mutual)
    {
        $this->authorize('suspend', $mutual);

        $mutual->update(['status' => 'suspended']);

        return back()->with('success', __('mxconnect.mutual.suspended', ['name' => $mutual->name]));
    }
}
