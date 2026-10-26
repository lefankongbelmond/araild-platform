<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Complaint;
use App\Models\Tenant\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/** Complaint register (CDC Phase 2, §24) with an SLA due date. */
class ComplaintController extends Controller
{
    private function authorizeGov(Request $request): void
    {
        abort_unless($request->user()->hasAnyRole(['mutual_admin', 'controller_validator', 'enrollment_agent']), 403);
    }

    public function index(Request $request)
    {
        $this->authorizeGov($request);
        $status = $request->get('status');

        $complaints = Complaint::query()->with('member')
            ->when(in_array($status, ['received', 'in_progress', 'resolved', 'closed']), fn ($q) => $q->where('status', $status))
            ->orderByDesc('received_on')->orderByDesc('id')->paginate(25)->withQueryString();

        return view('tenant.complaints.index', compact('complaints', 'status'));
    }

    public function store(Request $request)
    {
        $this->authorizeGov($request);
        $data = $request->validate([
            'member_id'   => ['nullable', 'integer', 'exists:members,id'],
            'channel'     => ['required', 'in:phone,in_person,written,other'],
            'subject'     => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'received_on' => ['required', 'date'],
        ]);

        $slaDays = (int) config('mxconnect.governance.complaint_sla_days', 15);

        Complaint::create($data + [
            'ref'    => 'RCL-' . now()->format('y') . '-' . strtoupper(Str::random(4)),
            'status' => 'received',
            'due_on' => Carbon::parse($data['received_on'])->addDays($slaDays)->toDateString(),
        ]);

        return back()->with('success', __('mxconnect.complaint.created'));
    }

    public function show(Complaint $complaint)
    {
        return view('tenant.complaints.show', compact('complaint'));
    }

    public function updateStatus(Request $request, Complaint $complaint)
    {
        $this->authorizeGov($request);
        $data = $request->validate([
            'status'     => ['required', 'in:received,in_progress,resolved,closed'],
            'resolution' => ['nullable', 'string'],
        ]);

        if (in_array($data['status'], ['resolved', 'closed'], true) && blank($data['resolution'] ?? $complaint->resolution)) {
            return back()->with('error', __('mxconnect.complaint.need_resolution'));
        }

        $complaint->update([
            'status'      => $data['status'],
            'resolution'  => $data['resolution'] ?? $complaint->resolution,
            'resolved_on' => in_array($data['status'], ['resolved', 'closed'], true) ? now()->toDateString() : null,
        ]);

        return back()->with('success', __('mxconnect.complaint.updated'));
    }

    public function members()
    {
        return Member::orderBy('last_name')->limit(500)->get(['id', 'member_code', 'first_name', 'last_name']);
    }
}
