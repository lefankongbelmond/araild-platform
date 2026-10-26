<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Central\Mutual;
use App\Models\Central\MutualPaymentConfig;
use App\Models\Tenant\ContributionSchedule;
use App\Payments\Contracts\PaymentDriver;
use App\Services\PaymentLedgerService;
use Illuminate\Http\Request;

/**
 * Initiates a Mobile Money payment for a schedule (facilitation model).
 * Money is collected to the MUTUAL's merchant account; MX-CONNECT only records
 * the intent in the central ledger and asks the driver to request the debit.
 */
class PaymentInitiationController extends Controller
{
    public function __construct(private PaymentLedgerService $ledger) {}

    public function initiate(Request $request, ContributionSchedule $schedule)
    {
        $request->validate([
            'provider_id' => ['required', 'integer'],
            'payer_phone' => ['required', 'string', 'max:20'],
        ]);

        abort_if($schedule->status === 'paid', 422);

        $schedule->loadMissing('subscription.member');
        $member = $schedule->subscription->member;

        $mutual = Mutual::find(tenant('id'));
        $config = MutualPaymentConfig::where('mutual_id', $mutual->id)
            ->where('payment_provider_id', $request->integer('provider_id'))
            ->where('active', true)
            ->firstOrFail();

        // 1) record the intent in the central ledger (idempotency reference generated here)
        $tx = $this->ledger->initiate(
            mutual: $mutual,
            publicAccountId: 0,
            memberId: $member->id,
            scheduleId: $schedule->id,
            amountMinor: $schedule->due_minor,
            currencyId: (int) tenant('currency_id'),
            providerId: $request->integer('provider_id'),
        );

        // 2) ask the driver to request the debit toward the mutual's account
        $driver = app(PaymentDriver::class);
        $providerRef = $driver->requestPayment($tx, $config, $request->string('payer_phone'));
        $tx->update(['status' => 'pending', 'provider_ref' => $providerRef]);

        return back()->with('success', __('mxconnect.payment.initiated'));
    }
}
