<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/** Délivrance en pharmacie (2026-11-19) : une prestation, ses lignes de médicaments. */
class Dispensation extends Model implements Auditable
{
    use AuditableTrait;

    protected $guarded = [];
    protected $casts = ['dispensed_on' => 'date', 'total_minor' => 'integer', 'excluded_minor' => 'integer'];

    public function prescription() { return $this->belongsTo(Prescription::class); }
    public function prestation() { return $this->belongsTo(Prestation::class); }
    public function lines() { return $this->hasMany(DispensationLine::class); }
}
