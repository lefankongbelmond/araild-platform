<?php

namespace App\Payments\Drivers;

use App\Models\Central\MutualPaymentConfig;
use App\Models\Central\PaymentTransaction;
use App\Payments\Contracts\PaymentDriver;
use Illuminate\Support\Str;

/**
 * Sandbox driver for development and tests. Simulates a real aggregator:
 * returns a provider reference on request and validates webhooks with a shared
 * secret HMAC. Replace with a real driver (per provider) in production.
 */
class SandboxDriver implements PaymentDriver
{
    public function requestPayment(PaymentTransaction $transaction, MutualPaymentConfig $config, string $payerPhone): ?string
    {
        // In production this is an HTTP call to the aggregator using $config->merchant_id
        // and $config->credentials, with the mutual as the collection account.
        return 'SBX-' . Str::upper(Str::random(10));
    }

    public function verifyWebhook(array $headers, string $rawBody, MutualPaymentConfig $config): bool
    {
        $secret = data_get($config->credentials, 'webhook_secret', 'sandbox-secret');
        $expected = hash_hmac('sha256', $rawBody, $secret);
        $given = $headers['x-signature'][0] ?? ($headers['X-Signature'][0] ?? '');
        return is_string($given) && hash_equals($expected, $given);
    }

    public function parseWebhook(array $payload): array
    {
        return [
            $payload['reference'] ?? '',
            ($payload['status'] ?? '') === 'success',
            $payload['provider_ref'] ?? null,
        ];
    }
}
