<?php

namespace App\Http\Controllers;

use App\Jobs\ReconcilePayment;
use App\Models\Central\MutualPaymentConfig;
use App\Models\Central\PaymentProvider;
use App\Models\Central\PaymentTransaction;
use App\Payments\Contracts\PaymentDriver;
use App\Services\PaymentLedgerService;
use Illuminate\Http\Request;

/**
 * Aggregator webhook (CENTRAL). The aggregator posts back the reference we
 * generated. We look the transaction up in the central ledger, verify the
 * signature against the owning mutual's config, then apply the result
 * idempotently. Reconciliation is queued (tenant-aware).
 */
class PaymentWebhookController extends Controller
{
    public function __construct(private PaymentLedgerService $ledger) {}

    public function handle(Request $request, string $provider)
    {
        $driver = app(PaymentDriver::class);
        $payload = $request->all();

        [$reference, $success, $providerRef] = $driver->parseWebhook($payload);

        /** @var PaymentTransaction|null $tx */
        $tx = PaymentTransaction::where('reference', $reference)->first();
        if (! $tx) {
            return response()->json(['ok' => true]);   // unknown ref: ack and ignore
        }

        // Verify signature against THIS mutual's payment config.
        $config = MutualPaymentConfig::where('mutual_id', $tx->mutual_id)
            ->whereHas('provider', fn ($q) => $q->where('code', $provider))
            ->first();
        if (! $config || ! $driver->verifyWebhook($request->headers->all(), $request->getContent(), $config)) {
            return response()->json(['ok' => false, 'error' => 'invalid_signature'], 403);
        }

        // Idempotent: returns false if already confirmed (no double count).
        $applied = $this->ledger->handleWebhook($reference, $success, (string) $providerRef, $payload);

        if ($applied && $success) {
            ReconcilePayment::dispatch($reference);
        }

        return response()->json(['ok' => true]);
    }
}
