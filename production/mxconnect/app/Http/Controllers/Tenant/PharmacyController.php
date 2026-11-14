<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Central\CareProvider;
use App\Models\Central\Currency;
use App\Models\Tenant\Dispensation;
use App\Models\Tenant\Medicine;
use App\Models\Tenant\MutualSetting;
use Illuminate\Http\Request;

/**
 * Pharmacie côté mutuelle (2026-11-19) : registre des délivrances (ordonnance, médicaments, lignes
 * non prises en charge) ; politique pharmaceutique (réglages de la mutuelle) et liste des médicaments
 * pris en charge (liste positive, exclusions, génériques, quantité maximale par ordonnance).
 */
class PharmacyController extends Controller
{
    public function index(Request $request)
    {
        \App\Support\HealthAccessLog::record('pharmacy_registry');
        $q = trim((string) $request->get('q'));

        return view('tenant.pharmacy.index', [
            'items' => Dispensation::with(['prescription.member', 'prescription.dependent', 'prestation', 'lines.medicine'])
                ->when($q !== '', fn ($w) => $w->whereHas('prescription', fn ($r) => $r->where('reference', 'like', '%' . $q . '%')
                    ->orWhereHas('member', fn ($m) => $m->where('member_code', $q)->orWhere('last_name', 'like', $q . '%'))))
                ->latest('id')->paginate(25)->withQueryString(),
            'providers' => CareProvider::on('central')->whereIn('id', Dispensation::distinct()->pluck('provider_id'))->pluck('name', 'id'),
            'currency' => $this->currency(), 'q' => $q,
        ]);
    }

    public function policy()
    {
        return view('tenant.pharmacy.policy', [
            'settings' => collect(MutualSetting::KEYS)->mapWithKeys(fn ($v, $k) => [$k => MutualSetting::get($k)]),
            'medicines' => Medicine::orderBy('category')->orderBy('label')->get(),
        ]);
    }

    public function updatePolicy(Request $request)
    {
        $rules = [];
        foreach (array_keys(MutualSetting::KEYS) as $k) {
            $rules[$k] = ['nullable', 'integer', 'between:1,3650'];
        }
        $d = $request->validate($rules);
        foreach (array_keys(MutualSetting::KEYS) as $k) {
            MutualSetting::put($k, isset($d[$k]) ? (string) $d[$k] : null, $request->user()->id);
        }

        return back()->with('success', 'Politique pharmaceutique enregistrée.');
    }

    /** Liste des médicaments pris en charge : enregistrée en une fois. */
    public function updateMedicines(Request $request)
    {
        $d = $request->validate([
            'm' => ['required', 'array'],
            'm.*.eligible' => ['nullable', 'boolean'],
            'm.*.excluded' => ['nullable', 'boolean'],
            'm.*.generic' => ['nullable', 'boolean'],
            'm.*.max_quantity' => ['nullable', 'integer', 'between:1,9999'],
        ]);
        $n = 0;
        foreach (Medicine::whereIn('id', array_map('intval', array_keys($d['m'])))->get() as $med) {
            $row = $d['m'][$med->id];
            $new = ['eligible' => (bool) ($row['eligible'] ?? false), 'excluded' => (bool) ($row['excluded'] ?? false),
                'generic' => (bool) ($row['generic'] ?? false), 'max_quantity' => ($row['max_quantity'] ?? null) ?: null];
            $med->fill($new);
            if ($med->isDirty()) {
                $med->save();
                $n++;
            }
        }

        return back()->with('success', "Liste des médicaments enregistrée ({$n} modification(s)).");
    }

    private function currency(): Currency
    {
        return Currency::on('central')->find(tenant('currency_id'))
            ?? Currency::on('central')->where('code', 'XAF')->firstOrFail();
    }
}
