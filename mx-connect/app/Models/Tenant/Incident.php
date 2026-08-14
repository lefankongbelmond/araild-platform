<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Incident extends Model implements Auditable
{
    use AuditableTrait;

    protected $guarded = [];
    protected $casts = [
        'occurred_on' => 'date',
        'detected_on' => 'date',
        'resolved_on' => 'date',
    ];
}
