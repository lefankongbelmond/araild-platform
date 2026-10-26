<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Models\Central\DrTest;
use App\Services\DrAssessmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Disaster-recovery drill log (CDC Phase 2, §28). Super-admin only. */
class DrTestController extends Controller
{
    public function __construct(private DrAssessmentService $assessment) {}

    public function index()
    {
        $this->authorize('manage-config');

        return view('network.dr.index', [
            'tests'      => DrTest::orderByDesc('performed_on')->orderByDesc('id')->paginate(25),
            'assessment' => $this->assessment,
            'defaults'   => config('mxconnect.dr'),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('manage-config');

        $data = $request->validate([
            'type'               => ['required', 'in:backup_restore,failover,full_dr,tabletop'],
            'scope'              => ['required', 'string', 'max:200'],
            'performed_on'       => ['required', 'date'],
            'rto_target_minutes' => ['nullable', 'integer', 'min:0'],
            'rto_actual_minutes' => ['nullable', 'integer', 'min:0'],
            'rpo_target_minutes' => ['nullable', 'integer', 'min:0'],
            'rpo_actual_minutes' => ['nullable', 'integer', 'min:0'],
            'outcome'            => ['required', 'in:success,partial,failed'],
            'findings'           => ['nullable', 'string'],
            'performed_by'       => ['nullable', 'string', 'max:120'],
        ]);

        DrTest::create($data + ['ref' => 'DRT-' . now()->format('y') . '-' . strtoupper(Str::random(4))]);

        return back()->with('success', __('mxconnect.dr.created'));
    }

    public function show(DrTest $drTest)
    {
        $this->authorize('manage-config');

        return view('network.dr.show', ['test' => $drTest, 'assessment' => $this->assessment]);
    }
}
