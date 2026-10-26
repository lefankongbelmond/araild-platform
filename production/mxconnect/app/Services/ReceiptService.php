<?php

namespace App\Services;

use App\Models\Central\Currency;
use App\Models\Tenant\ContributionPayment;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

/**
 * Generates a numbered receipt for a confirmed contribution payment (CDC §13).
 *
 * Renders a Blade view to HTML and stores it on the tenant's disk. HTML is
 * chosen so the receipt prints cleanly from any device; a PDF engine (e.g.
 * dompdf) can be dropped in later without changing callers — swap the render step.
 */
class ReceiptService
{
    public function generate(ContributionPayment $payment): string
    {
        $payment->loadMissing('schedule.subscription.member');

        $currency = Currency::on('central')->find(tenant('currency_id'))
            ?? Currency::on('central')->where('code', 'XAF')->firstOrFail();

        $number = 'REC-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));

        $html = View::make('tenant.contributions.receipt', [
            'payment'  => $payment,
            'number'   => $number,
            'currency' => $currency,
            'mutual'   => tenant('name'),
        ])->render();

        $path = "receipts/{$number}.html";
        Storage::disk('local')->put($path, $html);   // tenant-scoped disk (config/tenancy.php)

        $payment->update(['receipt_path' => $path]);

        return $path;
    }
}
