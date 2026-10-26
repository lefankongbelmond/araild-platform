<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\MedicineRequest;
use App\Models\Central\Currency;
use App\Models\Tenant\Medicine;
use App\Models\Tenant\PrescribedMedicine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class MedicineController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q'));
        $category = (string) $request->get('categorie');
        $supplier = (string) $request->get('fournisseur');
        $currencyCode = (string) $request->get('devise');
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
        $default = $this->currency();
        $hasCatalog = Schema::hasColumn('medicines', 'category');

        $medicines = Medicine::query()
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('label', 'like', $like)->orWhere('code', 'like', $like)
                ->when(Schema::hasColumn('medicines', 'supplier'), fn ($y) => $y->orWhere('supplier', 'like', $like))))
            ->when($hasCatalog && isset(Medicine::CATEGORIES[$category]), fn ($w) => $w->where('category', $category))
            ->when($hasCatalog && $supplier !== '', fn ($w) => $w->where('supplier', $supplier))
            ->when($hasCatalog && $currencyCode !== '', fn ($w) => $currencyCode === $default->code
                ? $w->where(fn ($x) => $x->whereNull('currency_code')->orWhere('currency_code', $default->code))
                : $w->where('currency_code', $currencyCode))
            ->orderBy('label')->paginate(15)->withQueryString();

        $editing = $request->filled('modifier') ? Medicine::find((int) $request->get('modifier')) : null;
        $currencies = $this->currencies($default);

        return view('tenant.parameters.medicines', [
            'medicines'  => $medicines,
            'currency'   => $default,
            'currencies' => $currencies,
            'editing'    => $editing,
            'suppliers'  => $hasCatalog ? Medicine::whereNotNull('supplier')->distinct()->orderBy('supplier')->pluck('supplier') : collect(),
            'usage'      => PrescribedMedicine::whereIn('medicine_id', $medicines->pluck('id'))->selectRaw('medicine_id, count(*) as n')
                ->groupBy('medicine_id')->pluck('n', 'medicine_id'),
            'stats'      => [
                'total'      => Medicine::count(),
                'categories' => $hasCatalog ? Medicine::whereNotNull('category')->distinct()->count('category') : 0,
                'suppliers'  => $hasCatalog ? Medicine::whereNotNull('supplier')->distinct()->count('supplier') : 0,
                'currencies' => $hasCatalog ? Medicine::selectRaw('COALESCE(currency_code, ?) as c', [$default->code])->distinct()->count() : 1,
            ],
            'filters'    => compact('q', 'category', 'supplier', 'currencyCode'),
        ]);
    }

    public function store(MedicineRequest $request)
    {
        Medicine::create($this->payload($request->validated()));

        return redirect()->route('medicines.index')->with('success', 'Médicament enregistré.');
    }

    public function update(MedicineRequest $request, Medicine $medicine)
    {
        $medicine->update($this->payload($request->validated()));

        return redirect()->route('medicines.index', $request->only(['q', 'categorie', 'fournisseur', 'devise', 'page']))
            ->with('success', 'Médicament modifié.');
    }

    /** Suppression (corbeille) : les prestations passées gardent leur référence. */
    public function destroy(Request $request, Medicine $medicine)
    {
        abort_unless($request->user()?->can('manage-parameters'), 403);
        $medicine->delete();

        return redirect()->route('medicines.index', $request->only(['q', 'categorie', 'fournisseur', 'devise', 'page']))
            ->with('success', 'Médicament « ' . $medicine->label . ' » supprimé du catalogue. Les prestations déjà enregistrées ne sont pas modifiées.');
    }

    private function payload(array $data): array
    {
        $currency = Currency::on('central')->where('code', $data['currency_code'])->first() ?? $this->currency();

        return [
            'code'                  => $data['code'],
            'label'                 => $data['label'],
            'category'              => $data['category'],
            'presentation'          => $data['presentation'] ?? null,
            'supplier'              => isset($data['supplier']) ? trim($data['supplier']) ?: null : null,
            'reference_price_minor' => $currency->toMinor((float) $data['reference_price']),
            'currency_code'         => $currency->code,
            'description'           => $data['description'] ?? null,
        ];
    }

    /** Devises proposées : celle de la mutuelle d'abord, puis USD, EUR, puis les autres. */
    private function currencies(Currency $default)
    {
        $order = [$default->code, 'XAF', 'XOF', 'USD', 'EUR'];

        return Currency::on('central')->where('active', true)->get()
            ->sortBy(fn ($c) => [($i = array_search($c->code, $order, true)) === false ? 99 : $i, $c->name])->values();
    }

    /** Devise de la mutuelle (registre central). */
    private function currency(): Currency
    {
        return Currency::on('central')->find(tenant('currency_id'))
            ?? Currency::on('central')->where('code', 'XAF')->firstOrFail();
    }
}
