<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

/** Journal des vérifications de droits faites depuis le portail des prestataires (verdict seulement). */
class RightsCheck extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];
    protected $casts = ['ok' => 'boolean', 'reasons' => 'array'];

    public function member() { return $this->belongsTo(Member::class); }
}
