<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Budget extends Model implements Auditable
{
    use AuditableTrait;

    protected $guarded = [];
    protected $casts = ['adopted_on' => 'date', 'year' => 'integer'];

    public function lines() { return $this->hasMany(BudgetLine::class); }

    public function isAdopted(): bool { return $this->status === 'adopted'; }
}
