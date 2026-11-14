<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * GARANTIE d'une version de formule (ligne de couverture) : catégorie de soins (ou toutes),
 * catégorie de bénéficiaire, taux de prise en charge, ticket modérateur, conditions.
 * rule_key = identité stable d'une version de la formule à la suivante (consommations).
 */
class CoverageRule extends Model implements Auditable
{
    use AuditableTrait;

    protected $guarded = [];
    protected $casts = ['coverage_rate' => 'float', 'copay_rate' => 'float', 'emergency_exempt' => 'boolean', 'tariff_cap' => 'boolean', 'active' => 'boolean',
        'requires_authorization' => 'boolean', 'authorization_above_minor' => 'integer'];

    /** Accord préalable exigé pour ce montant facturé ? (2026-11-19) */
    public function needsAuthorization(int $totalMinor): bool
    {
        return (bool) $this->requires_authorization
            && ($this->authorization_above_minor === null || $totalMinor > $this->authorization_above_minor);
    }

    /** Catégories de bénéficiaires (calculées : âge, lien, élève, nouveau conjoint). */
    public const BENEFICIARY_CATEGORIES = [
        'all' => 'Tous les bénéficiaires', 'adult' => 'Adulte (18 ans et plus)', 'child_0_5' => 'Enfant de 0 à 5 ans',
        'child_6_17' => 'Enfant de 6 à 17 ans', 'student' => 'Élève', 'senior' => 'Senior (âge fixé dans la version)',
        'new_spouse' => 'Nouveau conjoint (ajouté après l’adhésion)',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $r) => $r->rule_key ??= (string) Str::ulid());
    }

    public function version() { return $this->belongsTo(GuaranteeVersion::class, 'guarantee_version_id'); }
    public function category() { return $this->belongsTo(ServiceCategory::class, 'service_category_id'); }
}
