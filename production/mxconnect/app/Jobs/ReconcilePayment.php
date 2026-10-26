<?php

namespace App\Jobs;

use App\Models\Central\Mutual;
use App\Models\Central\PaymentTransaction;
use App\Services\PaymentLedgerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Reconciles a confirmed payment inside the correct tenant database.
 * Dispatched by the webhook controller after a confirmed transaction. Being a
 * queued job keeps the webhook response fast; stancl re-initialises the tenant.
 */
class ReconcilePayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $reference) {}

    public function handle(PaymentLedgerService $ledger): void
    {
        /** @var PaymentTransaction|null $tx */
        $tx = PaymentTransaction::where('reference', $this->reference)->first();
        if (! $tx || $tx->status->value !== 'confirmed') {
            return;
        }

        $mutual = Mutual::find($tx->mutual_id);
        if (! $mutual) {
            $tx->update(['reconciliation_status' => 'discrepancy']);
            return;
        }

        // Run reconciliation inside the mutual's own database.
        $mutual->run(fn () => $ledger->reconcile($tx));
    }
}
