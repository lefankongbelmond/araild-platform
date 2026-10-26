<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Prestation extends Model implements Auditable
{
    use SoftDeletes, AuditableTrait;

    protected $guarded = [];
    protected $casts = [
        'care_date'              => 'date',
        'total_minor'            => 'integer',
        'mutual_part_minor'      => 'integer',
        'beneficiary_part_minor' => 'integer',
    ];

    public function careClaim()       { return $this->belongsTo(CareClaim::class); }
    public function actType()         { return $this->belongsTo(ActType::class); }
    public function guaranteeVersion(){ return $this->belongsTo(GuaranteeVersion::class); }
    public function medicines()       { return $this->hasMany(PrescribedMedicine::class); }
}
