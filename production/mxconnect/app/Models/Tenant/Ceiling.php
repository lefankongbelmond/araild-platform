<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

/** Plafond de prise en charge d'une version de garantie (global ou pour un acte). */
class Ceiling extends Model
{
    protected $guarded = [];
    protected $casts = ['ceiling_minor' => 'integer', 'act_type_id' => 'integer'];

    public const PERIODS = ['annual' => 'par an et par adhérent', 'per_act' => 'par acte', 'per_stay' => 'par séjour'];

    public function actType() { return $this->belongsTo(ActType::class); }
}
