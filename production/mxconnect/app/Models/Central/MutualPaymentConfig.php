<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;

/**
 * A mutual's OWN merchant credentials for a provider. Encrypted at rest.
 * MX-CONNECT uses these to direct the payment to the mutual — it never receives the funds.
 */
class MutualPaymentConfig extends Model
{
    protected $connection = 'central';
    protected $guarded = [];

    protected $casts = [
        'merchant_id' => 'encrypted',
        'credentials' => 'encrypted:array',
        'active' => 'boolean',
    ];

    public function provider() { return $this->belongsTo(PaymentProvider::class, 'payment_provider_id'); }
}
