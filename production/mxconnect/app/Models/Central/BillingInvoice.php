<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;

class BillingInvoice extends Model
{
    protected $connection = 'central';
    protected $guarded = [];
    protected $casts = [
        'amount_minor' => 'integer',
        'period_start' => 'date', 'period_end' => 'date',
        'issued_on' => 'date', 'due_on' => 'date', 'paid_on' => 'date',
    ];

    public function mutual()       { return $this->belongsTo(Mutual::class); }
    public function subscription() { return $this->belongsTo(BillingSubscription::class, 'billing_subscription_id'); }
    public function currency()     { return $this->belongsTo(Currency::class); }

    public function isOverdue(): bool
    {
        return $this->status !== 'paid' && $this->status !== 'void'
            && $this->due_on && $this->due_on->isPast();
    }
}
