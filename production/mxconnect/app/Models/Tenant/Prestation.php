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
        'captured_by'            => 'integer',
        'validated_by'           => 'integer',
    ];

    /** Origine d'une saisie : back-office de la mutuelle, ou portail du prestataire (2026-10-31). */
    public const SOURCES = ['mutual' => 'Mutuelle', 'provider' => 'Portail prestataire'];

    private static array $traceColumns = [];

    /** Colonnes captured_by / validated_by / source présentes ? (migration 2026_10_31 de la mutuelle) */
    public static function hasTraceColumns(): bool
    {
        $key = (string) (function_exists('tenant') ? tenant('id') : '');

        return self::$traceColumns[$key] ??= \Illuminate\Support\Facades\Schema::hasColumn('prestations', 'captured_by');
    }

    /** « Personne ne valide sa propre saisie » (administrateur compris). */
    public function isCapturedBy(?User $user): bool
    {
        return $user !== null && $this->captured_by !== null && (int) $this->captured_by === (int) $user->id;
    }

    public function careClaim()       { return $this->belongsTo(CareClaim::class); }
    public function actType()         { return $this->belongsTo(ActType::class); }
    public function guaranteeVersion(){ return $this->belongsTo(GuaranteeVersion::class); }
    public function medicines()       { return $this->hasMany(PrescribedMedicine::class); }
}
