<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Concerns\MutualContext;
use App\Http\Requests\ActTypeRequest;
use App\Models\Tenant\ActType;
use Illuminate\Http\Request;

class ActTypeController extends Controller
{
    use MutualContext;

    public function index(Request $request)
    {
        $q = trim((string) $request->get('q'));
        $cat = (string) $request->get('categorie');
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';

        return view('tenant.parameters.act-types', [
            'actTypes'   => ActType::query()
                ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('label', 'like', $like)->orWhere('code', 'like', $like)))
                ->when($cat !== '', fn ($w) => $w->where('category', $cat))
                ->orderBy('category')->orderBy('label')->get(),
            'categories' => ActType::whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'editing'    => $request->filled('modifier') ? ActType::find((int) $request->get('modifier')) : null,
            'currency'   => $this->currency(),
            'total'      => ActType::count(),
            'filters'    => compact('q', 'cat'),
        ]);
    }

    public function store(ActTypeRequest $request)
    {
        ActType::create($this->payload($request->validated()));

        return redirect()->route('act-types.index')->with('success', __('mxconnect.param.saved'));
    }

    public function update(ActTypeRequest $request, ActType $act_type)
    {
        $act_type->update($this->payload($request->validated()));

        return redirect()->route('act-types.index')->with('success', __('mxconnect.param.saved'));
    }

    private function payload(array $d): array
    {
        return [
            'code' => $d['code'], 'label' => $d['label'], 'category' => $d['category'] ?? null,
            'tariff_minor' => isset($d['tariff']) && $d['tariff'] !== null ? $this->currency()->toMinor((float) $d['tariff']) : null,
        ];
    }
}
