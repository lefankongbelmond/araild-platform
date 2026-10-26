<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Central\Mutual;
use App\Models\Central\PaymentTransaction;
use App\Models\Tenant\ContributionSchedule;
use Illuminate\Support\Str;

/**
 * FACILITATION model: MX-CONNECT never holds funds.
 * It initiates a payment toward the MUTUAL's own merchant account, then only
 * observes the aggregator's signed webhook and reconciles the schedule.
 *
 * The ledger is append-only. A correction is a counter-entry, never an edit.
 */
class PaymentLedgerService
{
    /**
     * Step 1 — initiate. Creates the ledger row and returns it. The actual call
     * to the aggregator (using the mutual's merchant credentials) is done by the
     * provider driver resolved from the mutual's payment config.
     */
    public function initiate(Mutual $mutual, int $publicAccountId, int $memberId, int $scheduleId, int $amountMinor, int $currencyId, int $providerId, string $type = 'contribution'): PaymentTransaction
    {
        return PaymentTransaction::create([
            'reference'           => (string) Str::uuid(),   // idempotency key
            'mutual_id'           => $mutual->id,
            'public_account_id'   => $publicAccountId,
            'member_id'           => $memberId,
            'schedule_id'         => $scheduleId,
            'payment_provider_id' => $providerId,
            'currency_id'         => $currencyId,
            'type'                => $type,
            'amount_minor'        => $amountMinor,
            'status'              => PaymentStatus::Initiated->value,
        ]);
        // Caller then invokes the provider driver:
        //   $driver->requestPayment($tx, $mutual->paymentConfigs()->where(...)->first());
        // which directs the debit to the MUTUAL's merchant account.
    }

    /**
     * Step 2 — handle a webhook. Idempotent: a reference already confirmed is a no-op.
     * Returns true if this call transitioned the transaction (first valid delivery).
     */
    public function handleWebhook(string $reference, bool $success, string $providerRef, array $payload): bool
    {
        /** @var PaymentTransaction|null $tx */
        $tx = PaymentTransaction::where('reference', $reference)->first();
        if (! $tx) {
            return false; // unknown reference — ignore (or log for investigation)
        }
        if ($tx->status === PaymentStatus::Confirmed) {
            return false; // idempotency: already processed, never double-count
        }

        $tx->update([
            'status'              => $success ? PaymentStatus::Confirmed->value : PaymentStatus::Failed->value,
            'provider_ref'        => $providerRef,
            'webhook_received_at' => now(),
            'webhook_payload'     => $payload,
        ]);

        // NOTE: reconciliation is intentionally NOT done here. This handler runs in
        // the CENTRAL context (no tenant DB initialised), so the caller dispatches
        // the tenant-aware ReconcilePayment job instead. Doing it inline would both
        // run against the wrong connection and double-reconcile.

        return true;
    }

    /**
     * Step 3 — reconcile inside the mutual's tenant DB. Marks the schedule paid,
     * records the treasury inflow and generates the receipt (delegated).
     * Runs tenant-aware: initialise tenancy for $tx->mutual_id before calling.
     */
    public function reconcile(PaymentTransaction $tx): void
    {
        // Idempotent: a replayed job must not create a second payment/movement.
        if ($tx->reconciliation_status === 'reconciled') {
            return;
        }

        // tenancy()->initialize($tx->mutual_id) is done by the calling job.
        $schedule = ContributionSchedule::find($tx->schedule_id);
        if (! $schedule) {
            $tx->update(['reconciliation_status' => 'discrepancy']);
            return;
        }

        $payment = $schedule->payments()->create([
            'transaction_reference' => $tx->reference,
            'amount_minor'          => $tx->amount_minor,
            'paid_on'               => now()->toDateString(),
            'mode'                  => 'mobile_money',
        ]);
        $schedule->update(['status' => 'paid']);

        // Record the treasury inflow (the money is in the mutual's account).
        \App\Models\Tenant\TreasuryMovement::create([
            'direction'        => 'inflow',
            'source'           => $tx->type === 'membership_fee' ? 'membership_fee' : 'contribution',
            'amount_minor'     => $tx->amount_minor,
            'mode'             => 'mobile_money',
            'linked_reference' => $tx->reference,
            'moved_on'         => now()->toDateString(),
        ]);

        // Numbered receipt.
        app(\App\Services\ReceiptService::class)->generate($payment);

        $tx->update(['reconciliation_status' => 'reconciled']);
    }
}
