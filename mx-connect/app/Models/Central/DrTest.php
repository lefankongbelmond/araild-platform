<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

/** A disaster-recovery drill record (central). */
class DrTest extends Model implements Auditable
{
    use AuditableTrait;

    protected $connection = 'central';
    protected $guarded = [];
    protected $casts = [
        'performed_on'       => 'date',
        'rto_target_minutes' => 'integer',
        'rto_actual_minutes' => 'integer',
        'rpo_target_minutes' => 'integer',
        'rpo_actual_minutes' => 'integer',
    ];
}
