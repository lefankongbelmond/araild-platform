<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class ContributionSchedule extends Model
{
    protected $guarded = [];
    protected $casts = ['due_date' => 'date', 'due_minor' => 'integer'];

    public function subscription() { return $this->belongsTo(Subscription::class); }
    public function payments()     { return $this->hasMany(ContributionPayment::class, 'schedule_id'); }
}
