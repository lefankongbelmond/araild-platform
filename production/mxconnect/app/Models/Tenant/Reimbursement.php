<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Reimbursement extends Model implements Auditable
{
    use SoftDeletes, AuditableTrait;

    protected $guarded = [];
    protected $casts = [
        'requested_minor' => 'integer',
        'granted_minor'   => 'integer',
        'supporting_docs' => 'array',
    ];

    public function careClaim() { return $this->belongsTo(CareClaim::class); }
}
