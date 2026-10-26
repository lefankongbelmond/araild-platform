<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class FiscalYear extends Model
{
    protected $guarded = [];
    protected $casts = ['start_date' => 'date', 'end_date' => 'date'];

    public function periods() { return $this->hasMany(AccountingPeriod::class); }
}
