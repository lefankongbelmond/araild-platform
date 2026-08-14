<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\DependentRequest;
use App\Http\Requests\MemberRequest;
use App\Models\Tenant\Antenna;
use App\Models\Tenant\Dependent;
use App\Models\Tenant\Member;
use App\Models\Tenant\MemberGroup;
use App\Services\DuplicateDetectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MemberController extends Controller
{
    public function __construct(private DuplicateDetectionService $duplicates) {}

    public function index(Request $request)
    {
        $q = trim((string) $request->get('q'));

        $members = Member::query()
            ->when($q, function ($query) use ($q) {
                $query->where(fn ($sub) => $sub
                    ->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('member_code', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%"));
            })
            ->withCount(['dependents' => fn ($d) => $d->where('status', 'active')])
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('tenant.members.index', compact('members', 'q'));
    }

    public function create()
    {
        return view('tenant.members.create', [
            'antennas' => Antenna::orderBy('name')->get(),
            'groups'   => MemberGroup::orderBy('name')->get(),
        ]);
    }

    public function store(MemberRequest $request)
    {
        $data = $request->validated();

        $result = $this->duplicates->check(
            $data['first_name'], $data['last_name'], $data['birth_date'],
            $data['phone'] ?? null, $data['id_document'] ?? null,
        );

        // Strong match: hard stop, never allow — point the agent to the existing record.
        if ($result['block']) {
            return back()->withInput()->with('duplicate_block', $result['strong']);
        }

        // Weak match: allow only if the agent explicitly acknowledged the warning.
        if ($result['warn'] && ! $request->boolean('confirm_duplicate')) {
            return back()->withInput()->with('duplicate_warn', $result['weak']);
        }

        $member = Member::create([
            'member_code'     => $this->generateCode(),
            'antenna_id'      => $data['antenna_id'] ?? null,
            'member_group_id' => $data['member_group_id'] ?? null,
            'first_name'      => $data['first_name'],
            'last_name'       => $data['last_name'],
            'birth_date'      => $data['birth_date'],
            'sex'             => $data['sex'],
            'phone'           => $data['phone'] ?? null,
            'id_document'     => $data['id_document'] ?? null,   // encrypted by the model cast
            'address'         => $data['address'] ?? null,
            'status'          => 'active',
            'joined_at'       => now()->toDateString(),
        ]);

        return redirect()->route('members.show', $member)
            ->with('success', __('mxconnect.member.created', ['code' => $member->member_code]));
    }

    public function show(Member $member)
    {
        return view('tenant.members.show', [
            'member' => $member->load(['dependents' => fn ($q) => $q->orderBy('last_name'), 'group', 'subscriptions']),
        ]);
    }

    public function update(MemberRequest $request, Member $member)
    {
        $data = $request->validated();

        $result = $this->duplicates->check(
            $data['first_name'], $data['last_name'], $data['birth_date'],
            $data['phone'] ?? null, $data['id_document'] ?? null,
            ignoreId: $member->id,
        );
        if ($result['block']) {
            return back()->withInput()->with('duplicate_block', $result['strong']);
        }

        $member->update($data);

        return redirect()->route('members.show', $member)->with('success', __('mxconnect.member.updated'));
    }

    // --- lifecycle transitions (validated / audited) ---

    public function suspend(Member $member)
    {
        $this->authorizeChange();
        abort_unless($member->status->value === 'active', 422);
        $member->update(['status' => 'suspended']);
        return back()->with('success', __('mxconnect.member.suspended'));
    }

    public function reactivate(Member $member)
    {
        $this->authorizeChange();
        abort_unless($member->status->value === 'suspended', 422);
        $member->update(['status' => 'active']);
        return back()->with('success', __('mxconnect.member.reactivated'));
    }

    // --- dependents ---

    public function addDependent(DependentRequest $request, Member $member)
    {
        $member->dependents()->create($request->validated() + ['status' => 'active']);
        return back()->with('success', __('mxconnect.member.dependent_added'));
    }

    public function removeDependent(Member $member, Dependent $dependent)
    {
        $this->authorizeChange();
        abort_unless($dependent->member_id === $member->id, 404);
        $dependent->update(['status' => 'removed']);
        return back()->with('success', __('mxconnect.member.dependent_removed'));
    }

    private function authorizeChange(): void
    {
        abort_unless(request()->user()?->hasAnyRole(['enrollment_agent', 'mutual_admin']), 403);
    }

    /** Human-readable, collision-resistant member code. */
    private function generateCode(): string
    {
        do {
            $code = 'ADH-' . now()->format('y') . '-' . strtoupper(Str::random(5));
        } while (Member::where('member_code', $code)->exists());

        return $code;
    }
}
