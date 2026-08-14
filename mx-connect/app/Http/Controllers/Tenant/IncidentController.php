<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Incident;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Incident log (CDC Phase 2, §23). */
class IncidentController extends Controller
{
    private function authorizeGov(Request $request): void
    {
        abort_unless($request->user()->hasAnyRole(['mutual_admin', 'controller_validator', 'security_admin']), 403);
    }

    public function index(Request $request)
    {
        $this->authorizeGov($request);
        $status = $request->get('status');

        $incidents = Incident::query()
            ->when(in_array($status, ['open', 'investigating', 'resolved', 'closed']), fn ($q) => $q->where('status', $status))
            ->orderByDesc('occurred_on')->orderByDesc('id')->paginate(25)->withQueryString();

        return view('tenant.incidents.index', compact('incidents', 'status'));
    }

    public function store(Request $request)
    {
        $this->authorizeGov($request);
        $data = $request->validate([
            'title'       => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'category'    => ['required', 'in:operational,security,fraud,data_breach,service,other'],
            'severity'    => ['required', 'in:low,medium,high,critical'],
            'occurred_on' => ['required', 'date'],
            'detected_on' => ['nullable', 'date'],
        ]);

        Incident::create($data + [
            'ref'    => 'INC-' . now()->format('y') . '-' . strtoupper(Str::random(4)),
            'status' => 'open',
        ]);

        return back()->with('success', __('mxconnect.incident.created'));
    }

    public function show(Incident $incident)
    {
        return view('tenant.incidents.show', compact('incident'));
    }

    public function updateStatus(Request $request, Incident $incident)
    {
        $this->authorizeGov($request);
        $data = $request->validate([
            'status'     => ['required', 'in:open,investigating,resolved,closed'],
            'resolution' => ['nullable', 'string'],
        ]);

        // Resolution text is required to mark resolved/closed.
        if (in_array($data['status'], ['resolved', 'closed'], true) && blank($data['resolution'] ?? $incident->resolution)) {
            return back()->with('error', __('mxconnect.incident.need_resolution'));
        }

        $incident->update([
            'status'      => $data['status'],
            'resolution'  => $data['resolution'] ?? $incident->resolution,
            'resolved_on' => in_array($data['status'], ['resolved', 'closed'], true) ? now()->toDateString() : null,
        ]);

        return back()->with('success', __('mxconnect.incident.updated'));
    }
}
