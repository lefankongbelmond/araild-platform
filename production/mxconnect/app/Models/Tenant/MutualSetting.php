<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Réglages de la mutuelle (2026-11-19), clé → valeur. Une clé absente = règle non appliquée :
 * aucune valeur par défaut n'est inventée.
 */
class MutualSetting extends Model
{
    protected $guarded = [];

    /** Réglages connus : clé => [libellé, type]. */
    public const KEYS = [
        'prescription_validity_days' => ['Validité d’une ordonnance (jours après sa date)', 'int'],
        'dispensation_watch_days'    => ['Délivrances rapprochées : signaler un même médicament redélivré dans les (jours)', 'int'],
        'preauth_validity_days'      => ['Validité d’un accord préalable (jours après la décision)', 'int'],
    ];

    public static function get(string $key): ?string
    {
        if (! Schema::hasTable('mutual_settings')) {
            return null;
        }

        return static::where('key', $key)->value('value');
    }

    public static function int(string $key): ?int
    {
        $v = static::get($key);

        return $v === null || $v === '' ? null : (int) $v;
    }

    public static function put(string $key, ?string $value, ?int $by = null): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value === '' ? null : $value, 'updated_by' => $by]);
    }
}
