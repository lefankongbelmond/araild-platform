<?php

namespace App\Payments\Contracts;

use App\Models\Central\MutualPaymentConfig;
use App\Models\Central\PaymentTransaction;

/**
 * A payment driver directs a debit to the MUTUAL's own merchant account.
 * MX-CONNECT never receives the funds — the driver only requests the collection
 * and later the aggregator calls our webhook. Concrete drivers wrap a specific
 * aggregator (MTN MoMo, Orange Money, Campay, Fapshi, Flutterwave, ...).
 */
interface PaymentDriver
{
    /**
     * Request a payment. Returns a provider reference (or null on immediate reject).
     * Implementations must use $config (the mutual's merchant credentials) as the
     * collection target — the money goes to the mutual, not to MX-CONNECT.
     */
    public function requestPayment(PaymentTransaction $transaction, MutualPaymentConfig $config, string $payerPhone): ?string;

    /** Verify an inbound webhook signature. Returns true if authentic. */
    public function verifyWebhook(array $headers, string $rawBody, MutualPaymentConfig $config): bool;

    /** Parse a webhook body into [reference, success, providerRef]. */
    public function parseWebhook(array $payload): array;
}
