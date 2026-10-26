<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class ContributionPayment extends Model
{
    protected $guarded = [];
    protected $casts = ['paid_on' => 'date', 'amount_minor' => 'integer'];

    public function schedule() { return $this->belongsTo(ContributionSchedule::class, 'schedule_id'); }
}
