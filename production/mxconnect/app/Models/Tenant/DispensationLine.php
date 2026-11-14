<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class DispensationLine extends Model
{
    protected $guarded = [];
    protected $casts = ['eligible' => 'boolean', 'above_reference' => 'boolean'];

    public const REASONS = ['excluded' => 'Médicament exclu', 'not_listed' => 'Hors liste des médicaments pris en charge'];

    public function medicine() { return $this->belongsTo(Medicine::class)->withTrashed(); }
    public function dispensation() { return $this->belongsTo(Dispensation::class); }
}
