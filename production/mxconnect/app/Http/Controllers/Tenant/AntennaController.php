<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\AntennaRequest;
use App\Models\Tenant\Antenna;

class AntennaController extends Controller
{
    public function index()
    {
        return view('tenant.parameters.antennas', ['antennas' => Antenna::orderBy('name')->get()]);
    }

    public function store(AntennaRequest $request)
    {
        Antenna::create($request->validated());
        return back()->with('success', __('mxconnect.param.saved'));
    }

    public function update(AntennaRequest $request, Antenna $antenna)
    {
        $antenna->update($request->validated());
        return back()->with('success', __('mxconnect.param.saved'));
    }

    public function destroy(Antenna $antenna)
    {
        $this->authorizeGate();
        $antenna->delete();
        return back()->with('success', __('mxconnect.param.deleted'));
    }

    private function authorizeGate(): void
    {
        abort_unless(request()->user()?->can('manage-parameters'), 403);
    }
}
