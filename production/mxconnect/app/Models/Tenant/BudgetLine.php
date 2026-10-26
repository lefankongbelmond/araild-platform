<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class BudgetLine extends Model implements Auditable
{
    use AuditableTrait;

    protected $guarded = [];
    protected $casts = ['amount_minor' => 'integer'];

    public function budget()  { return $this->belongsTo(Budget::class); }
    public function account() { return $this->belongsTo(Account::class); }
}
