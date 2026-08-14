<?php

namespace App\Http\Controllers\MemberApi;

use App\Http\Controllers\Controller;
use App\Models\Central\Currency;
use App\Models\Central\Mutual;
use App\Models\Central\MutualPaymentConfig;
use App\Models\Tenant\ContributionSchedule;
use App\Payments\Contracts\PaymentDriver;
use App\Services\DigitalCardService;
use App\Services\PaymentLedgerService;
use Illuminate\Http\Request;

/**
 * Read-only member self-service + contribution payment. All endpoints resolve the
 * caller's member from their Sanctum token (auth:sanctum) within this mutual.
 */
class MemberApiController extends Controller
{
    use ResolvesMember;

    public function me(Request $request)
    {
        $member = $this->requireMember($request);

        return response()->json([
            'code'       => $member->member_code,
            'first_name' => $member->first_name,
            'last_name'  => $member->last_name,
            'status'     => $member->status->value,
            'dependents' => $member->dependents()->where('status', 'active')->count(),
        ]);
    }

    public function card(Request $request, DigitalCardService $cards)
    {
        $member = $this->requireMember($request);
        return response()->json([
            'token'      => $cards->issueToken($member),
            'verify_url' => route('members.card.verify', ['t' => $cards->issueToken($member)]),
        ]);
    }

    public function schedules(Request $request)
    {
        $member = $this->requireMember($request);
        $currency = $this->currency();

        $schedules = ContributionSchedule::whereHas('subscription', fn ($q) => $q->where('member_id', $member->id))
            ->orderBy('due_date')->get()
            ->map(fn ($s) => [
                'id'       => $s->id,
                'period'   => $s->period,
                'due_date' => $s->due_date?->toDateString(),
                'amount'   => $currency->format($s->due_minor),
                'status'   => $s->status,
            ]);

        return response()->json(['schedules' => $schedules]);
    }

    public function claims(Request $request)
    {
        $member = $this->requireMember($request);
        $currency = $this->currency();

        $claims = $member->careClaims()->with('prestations')->latest('opened_on')->get()
            ->map(fn ($c) => [
                'id'        => $c->id,
                'opened_on' => $c->opened_on?->toDateString(),
                'status'    => $c->status,
                'mutual_part' => $currency->format($c->mutualTotalMinor()),
            ]);

        return response()->json(['claims' => $claims]);
    }

    /** Initiate a Mobile Money payment for one of the member's own schedules. */
    public function pay(Request $request, ContributionSchedule $schedule, PaymentLedgerService $ledger)
    {
        $member = $this->requireMember($request);

        // The schedule must belong to the caller.
        abort_unless($schedule->subscription?->member_id === $member->id, 403);
        abort_if($schedule->status === 'paid', 422);

        $data = $request->validate([
            'provider_id' => ['required', 'integer'],
            'payer_phone' => ['required', 'string', 'max:20'],
        ]);

        $mutual = Mutual::find(tenant('id'));
        $config = MutualPaymentConfig::where('mutual_id', $mutual->id)
            ->where('payment_provider_id', $data['provider_id'])->where('active', true)->firstOrFail();

        $tx = $ledger->initiate(
            mutual: $mutual, publicAccountId: (int) $request->user()->id,
            memberId: $member->id, scheduleId: $schedule->id,
            amountMinor: $schedule->due_minor, currencyId: (int) tenant('currency_id'),
            providerId: (int) $data['provider_id'],
        );

        $ref = app(PaymentDriver::class)->requestPayment($tx, $config, $data['payer_phone']);
        $tx->update(['status' => 'pending', 'provider_ref' => $ref]);

        return response()->json(['status' => 'pending', 'reference' => $tx->reference]);
    }

    private function requireMember(Request $request)
    {
        $member = $this->currentMember($request->user());
        abort_unless($member, 403, __('mxconnect.pwa.not_a_member'));
        return $member;
    }

    private function currency(): Currency
    {
        return Currency::on('central')->find(tenant('currency_id'))
            ?? Currency::on('central')->where('code', 'XAF')->firstOrFail();
    }
}
