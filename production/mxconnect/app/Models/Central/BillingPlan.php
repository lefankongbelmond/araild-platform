<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;

class BillingPlan extends Model
{
    protected $connection = 'central';
    protected $guarded = [];
    protected $casts = ['features' => 'array', 'active' => 'boolean', 'price_minor' => 'integer'];

    public function currency()      { return $this->belongsTo(Currency::class); }
    public function subscriptions() { return $this->hasMany(BillingSubscription::class); }
}
