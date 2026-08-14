<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Risk;
use App\Services\RiskScoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Risk register (CDC Phase 2, §22). Role-gated to admin/controller. */
class RiskController extends Controller
{
    public function __construct(private RiskScoringService $scoring) {}

    private function authorizeGov(Request $request): void
    {
        abort_unless($request->user()->hasAnyRole(['mutual_admin', 'controller_validator', 'security_admin']), 403);
    }

    public function index(Request $request)
    {
        $this->authorizeGov($request);
        $status = $request->get('status');

        $risks = Risk::query()
            ->when(in_array($status, ['open', 'mitigating', 'closed']), fn ($q) => $q->where('status', $status))
            ->orderByRaw('likelihood * impact DESC')->orderByDesc('id')->paginate(25)->withQueryString();

        return view('tenant.risks.index', ['risks' => $risks, 'status' => $status, 'scoring' => $this->scoring]);
    }

    public function store(Request $request)
    {
        $this->authorizeGov($request);
        $data = $request->validate([
            'title'       => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'category'    => ['required', 'in:strategic,operational,financial,compliance,security,other'],
            'likelihood'  => ['required', 'integer', 'between:1,5'],
            'impact'      => ['required', 'integer', 'between:1,5'],
            'treatment'   => ['nullable', 'string'],
            'owner_role'  => ['nullable', 'string', 'max:60'],
            'review_on'   => ['nullable', 'date'],
        ]);

        Risk::create($data + [
            'ref'       => 'RSK-' . now()->format('y') . '-' . strtoupper(Str::random(4)),
            'status'    => 'open',
            'opened_on' => now()->toDateString(),
        ]);

        return back()->with('success', __('mxconnect.risk.created'));
    }

    public function show(Risk $risk)
    {
        return view('tenant.risks.show', ['risk' => $risk, 'scoring' => $this->scoring]);
    }

    public function updateStatus(Request $request, Risk $risk)
    {
        $this->authorizeGov($request);
        $data = $request->validate([
            'status'    => ['required', 'in:open,mitigating,closed'],
            'treatment' => ['nullable', 'string'],
        ]);

        $risk->update([
            'status'    => $data['status'],
            'treatment' => $data['treatment'] ?? $risk->treatment,
            'closed_on' => $data['status'] === 'closed' ? now()->toDateString() : null,
        ]);

        return back()->with('success', __('mxconnect.risk.updated'));
    }
}
