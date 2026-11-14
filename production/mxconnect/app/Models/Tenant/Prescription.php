<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/** Ordonnance (2026-11-19) : identifiée par son n°, le bénéficiaire et sa date (empreinte reference_key). */
class Prescription extends Model implements Auditable
{
    use AuditableTrait;

    protected $guarded = [];
    protected $casts = ['prescribed_on' => 'date', 'renewals_allowed' => 'integer'];

    public function member() { return $this->belongsTo(Member::class); }
    public function dependent() { return $this->belongsTo(Dependent::class); }
    public function dispensations() { return $this->hasMany(Dispensation::class); }

    /** Empreinte : n° normalisé (majuscules, sans espaces ni ponctuation) + bénéficiaire + date. */
    public static function keyFor(string $reference, int $memberId, ?int $dependentId, string $date): string
    {
        $norm = preg_replace('/[^A-Z0-9]/', '', mb_strtoupper(trim($reference)));

        return hash('sha256', $norm . '|' . $memberId . '|' . ($dependentId ?? 0) . '|' . $date);
    }
}
