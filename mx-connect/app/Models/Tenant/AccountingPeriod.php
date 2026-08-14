<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class AccountingPeriod extends Model
{
    protected $guarded = [];

    public function fiscalYear() { return $this->belongsTo(FiscalYear::class); }
    public function days()       { return $this->hasMany(AccountingDay::class); }
}
