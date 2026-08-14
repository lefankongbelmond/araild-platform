<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use App\Enums\PaymentStatus;

/**
 * Append-only ledger row. NEVER hard-deleted; corrections are counter-entries.
 */
class PaymentTransaction extends Model
{
    protected $connection = 'central';
    protected $guarded = [];

    protected $casts = [
        'status' => PaymentStatus::class,
        'webhook_payload' => 'array',
        'webhook_received_at' => 'datetime',
        'amount_minor' => 'integer',
    ];

    public function currency() { return $this->belongsTo(Currency::class); }
    public function provider() { return $this->belongsTo(PaymentProvider::class, 'payment_provider_id'); }
}
