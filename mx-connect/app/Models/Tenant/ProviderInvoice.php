<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class ProviderInvoice extends Model implements Auditable
{
    use SoftDeletes, AuditableTrait;

    protected $guarded = [];
    protected $casts = ['amount_minor' => 'integer'];

    public function careClaim() { return $this->belongsTo(CareClaim::class); }
}
