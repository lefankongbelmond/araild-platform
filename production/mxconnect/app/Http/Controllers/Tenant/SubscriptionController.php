<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubscriptionRequest;
use App\Models\Central\Currency;
use App\Models\Tenant\GuaranteeVersion;
use App\Models\Tenant\Member;
use App\Models\Tenant\Subscription;
use App\Services\DigitalCardService;
use App\Services\RightsVerificationService;
use App\Services\ScheduleGenerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SubscriptionController extends Controller
{
    public function index()
    {
        return view('tenant.subscriptions.index', [
            'subscriptions' => Subscription::with(['member', 'guaranteeVersion.guarantee'])
                ->orderByDesc('created_at')->paginate(20),
            'currency' => $this->currency(),
        ]);
    }

    public function create(Request $request)
    {
        $member = $request->filled('member') ? Member::findOrFail($request->get('member')) : null;

        return view('tenant.subscriptions.create', [
            'members'  => Member::where('status', 'active')->orderBy('last_name')->get(),
            'versions' => GuaranteeVersion::with('guarantee')->whereNull('valid_to')->get(),
            'selectedMember' => $member,
            'currency' => $this->currency(),
        ]);
    }

    /** MAKER — capture only. Amount is computed; nothing is active yet. */
    public function store(SubscriptionRequest $request)
    {
        $data = $request->validated();
        $member = Member::findOrFail($data['member_id']);
        $version = GuaranteeVersion::findOrFail($data['guarantee_version_id']);

        $contribution = $version->base_contribution_minor * $member->beneficiaryCount();
        $observationEnds = Carbon::parse($data['effective_date'])
            ->addDays((int) $version->observation_days)->toDateString();

        $subscription = Subscription::create([
            'member_id'            => $member->id,
            'guarantee_version_id' => $version->id,
            'contribution_minor'   => $contribution,
            'effective_date'       => $data['effective_date'],
            'observation_ends_on'  => $observationEnds,
            'status'               => 'captured',
            'captured_by'          => $request->user()->id,
        ]);

        return redirect()->route('subscriptions.show', $subscription)
            ->with('success', __('mxconnect.subscription.captured'));
    }

    public function show(Subscription $subscription)
    {
        return view('tenant.subscriptions.show', [
            'subscription' => $subscription->load(['member', 'guaranteeVersion.guarantee', 'schedules']),
            'currency'     => $this->currency(),
        ]);
    }

    /**
     * CHECKER — validate. Enforces separation of duties: the validator must be a
     * different user from the one who captured it. On success, generates the
     * schedule and makes the digital card available.
     */
    public function validateSubscription(Request $request, Subscription $subscription, ScheduleGenerationService $scheduler)
    {
        abort_unless($request->user()->can('subscription.validate'), 403);
        abort_unless($subscription->status->value === 'captured', 422);

        // Maker-checker: capturer cannot validate their own capture.
        if ($subscription->captured_by === $request->user()->id) {
            return back()->with('error', __('mxconnect.subscription.self_validate_blocked'));
        }

        $subscription->update([
            'status'       => 'validated',
            'validated_by' => $request->user()->id,
            'validated_at' => now(),
        ]);

        $scheduler->generate($subscription);

        return back()->with('success', __('mxconnect.subscription.validated'));
    }

    /** Printable digital card (QR encodes a signed token, no medical data). */
    public function card(Member $member, DigitalCardService $cards)
    {
        $subscription = $member->subscriptions()->where('status', 'validated')
            ->latest('effective_date')->firstOrFail();

        return view('tenant.card.show', [
            'member'       => $member,
            'subscription' => $subscription->load('guaranteeVersion.guarantee'),
            'token'        => $cards->issueToken($member),
        ]);
    }

    /** Provider-facing rights check from a scanned card. Returns verdict only. */
    public function verifyCard(Request $request, DigitalCardService $cards, RightsVerificationService $rights)
    {
        $result = $cards->verifyToken((string) $request->get('t'));
        if (! $result['valid']) {
            return view('tenant.card.verify', ['ok' => false, 'reasons' => ['invalid_card'], 'member' => null]);
        }

        $member = Member::find($result['member_id']);
        $subscription = $member?->subscriptions()->where('status', 'validated')->latest('effective_date')->first();
        $guaranteeId = $subscription?->guaranteeVersion?->guarantee_id;

        $verdict = ($member && $guaranteeId)
            ? $rights->verify($member, $guaranteeId, now()->toDateString())
            : ['ok' => false, 'reasons' => ['no_active_guarantee']];

        return view('tenant.card.verify', [
            'ok'      => $verdict['ok'],
            'reasons' => $verdict['reasons'],
            'member'  => $member,
        ]);
    }

    private function currency(): Currency
    {
        return Currency::on('central')->find(tenant('currency_id'))
            ?? Currency::on('central')->where('code', 'XAF')->firstOrFail();
    }
}
