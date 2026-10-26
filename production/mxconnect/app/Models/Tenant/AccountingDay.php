<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class AccountingDay extends Model
{
    protected $guarded = [];
    protected $casts = [
        'day' => 'date',
        'theoretical_balance_minor' => 'integer',
        'actual_balance_minor' => 'integer',
    ];

    public function period()  { return $this->belongsTo(AccountingPeriod::class, 'accounting_period_id'); }
    public function entries() { return $this->hasMany(AccountingEntry::class); }

    public function isClosed(): bool { return $this->status === 'closed'; }
}
