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
    public function dependent() { return $this->belongsTo(Dependent::class); }

    /** Colonnes de la phase 3 présentes (mutuelle migrée) ? */
    public static function hasIdentificationColumns(): bool
    {
        static $cache = [];
        $key = (string) (function_exists('tenant') ? tenant('id') : '');

        return $cache[$key] ??= \Illuminate\Support\Facades\Schema::hasColumn('rights_checks', 'dependent_id');
    }
}
