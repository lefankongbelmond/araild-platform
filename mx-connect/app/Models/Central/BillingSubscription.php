<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;

class BillingSubscription extends Model
{
    protected $connection = 'central';
    protected $guarded = [];
    protected $casts = [
        'started_on' => 'date', 'current_period_start' => 'date',
        'current_period_end' => 'date', 'trial_ends_on' => 'date',
    ];

    public function mutual()   { return $this->belongsTo(Mutual::class); }
    public function plan()     { return $this->belongsTo(BillingPlan::class, 'billing_plan_id'); }
    public function invoices() { return $this->hasMany(BillingInvoice::class); }
}
