<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Risk extends Model implements Auditable
{
    use AuditableTrait, SoftDeletes;

    protected $guarded = [];
    protected $casts = [
        'likelihood' => 'integer',
        'impact'     => 'integer',
        'opened_on'  => 'date',
        'review_on'  => 'date',
        'closed_on'  => 'date',
    ];

    /** Inherent risk score (likelihood × impact), 1..25. */
    public function score(): int
    {
        return (int) $this->likelihood * (int) $this->impact;
    }
}
