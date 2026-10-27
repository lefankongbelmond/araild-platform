<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class FiscalYear extends Model
{
    protected $guarded = [];
    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'closed_at' => 'datetime', 'result_minor' => 'integer', 'history' => 'array'];

    public function isClosed(): bool { return $this->status === 'closed'; }
    public function closedBy() { return $this->belongsTo(User::class, 'closed_by'); }

    public function periods() { return $this->hasMany(AccountingPeriod::class); }
}
