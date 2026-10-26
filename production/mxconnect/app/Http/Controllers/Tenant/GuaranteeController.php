<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuaranteeRequest;
use App\Http\Requests\GuaranteeVersionRequest;
use App\Models\Central\Currency;
use App\Models\Tenant\Guarantee;
use App\Services\GuaranteeVersioningService;

class GuaranteeController extends Controller
{
    public function __construct(private GuaranteeVersioningService $versioning) {}

    public function index()
    {
        return view('tenant.guarantees.index', [
            'guarantees' => Guarantee::with('currentVersion')->orderBy('name')->get(),
            'currency'   => $this->currency(),
        ]);
    }

    public function create()
    {
        return view('tenant.guarantees.create', ['currency' => $this->currency()]);
    }

    public function store(GuaranteeRequest $request)
    {
        $d = $request->validated();
        $cur = $this->currency();

        $this->versioning->createWithFirstVersion(
            guarantee: [
                'code' => $d['code'], 'name' => $d['name'],
                'description' => $d['description'] ?? null, 'status' => 'active',
            ],
            version: [
                'base_contribution_minor' => $cur->toMinor((float) $d['base_contribution']),
                'periodicity'             => $d['periodicity'],
                'coverage_rate'           => $d['coverage_rate'],
                'copay_rate'              => $d['copay_rate'],
                'membership_fee_minor'    => $cur->toMinor((float) ($d['membership_fee'] ?? 0)),
                'observation_days'        => $d['observation_days'],
                'valid_from'              => $d['valid_from'] ?? now()->toDateString(),
            ],
        );

        return redirect()->route('guarantees.index')->with('success', __('mxconnect.guarantee.created'));
    }

    public function show(Guarantee $guarantee)
    {
        return view('tenant.guarantees.show', [
            'guarantee' => $guarantee->load(['versions' => fn ($q) => $q->orderByDesc('version_no')]),
            'currency'  => $this->currency(),
        ]);
    }

    /** Roll to a new version — closes the current one, opens the successor. */
    public function newVersion(GuaranteeVersionRequest $request, Guarantee $guarantee)
    {
        $d = $request->validated();
        $cur = $this->currency();

        $this->versioning->newVersion($guarantee, [
            'base_contribution_minor' => $cur->toMinor((float) $d['base_contribution']),
            'periodicity'             => $d['periodicity'],
            'coverage_rate'           => $d['coverage_rate'],
            'copay_rate'              => $d['copay_rate'],
            'membership_fee_minor'    => $cur->toMinor((float) ($d['membership_fee'] ?? 0)),
            'observation_days'        => $d['observation_days'],
        ], effectiveFrom: $d['effective_from']);

        return redirect()->route('guarantees.show', $guarantee)
            ->with('success', __('mxconnect.guarantee.version_created'));
    }

    private function currency(): Currency
    {
        return Currency::on('central')->find(tenant('currency_id'))
            ?? Currency::on('central')->where('code', 'XAF')->firstOrFail();
    }
}
