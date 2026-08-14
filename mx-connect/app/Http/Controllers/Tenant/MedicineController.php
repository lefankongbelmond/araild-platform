<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\MedicineRequest;
use App\Models\Central\Currency;
use App\Models\Tenant\Medicine;

class MedicineController extends Controller
{
    public function index()
    {
        return view('tenant.parameters.medicines', [
            'medicines' => Medicine::orderBy('label')->get(),
            'currency'  => $this->currency(),
        ]);
    }

    public function store(MedicineRequest $request)
    {
        $data = $request->validated();
        Medicine::create([
            'code'  => $data['code'],
            'label' => $data['label'],
            'reference_price_minor' => $this->currency()->toMinor((float) $data['reference_price']),
        ]);
        return back()->with('success', __('mxconnect.param.saved'));
    }

    public function update(MedicineRequest $request, Medicine $medicine)
    {
        $data = $request->validated();
        $medicine->update([
            'code'  => $data['code'],
            'label' => $data['label'],
            'reference_price_minor' => $this->currency()->toMinor((float) $data['reference_price']),
        ]);
        return back()->with('success', __('mxconnect.param.saved'));
    }

    /** Current tenant's currency (from the central mutuals registry). */
    private function currency(): Currency
    {
        return Currency::on('central')->find(tenant('currency_id'))
            ?? Currency::on('central')->where('code', 'XAF')->firstOrFail();
    }
}
