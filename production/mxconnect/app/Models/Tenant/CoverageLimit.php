<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Plafond d'une version de formule : par acte / jour / mois / an, par bénéficiaire ou par famille,
 * en montant (part mutuelle) et/ou en nombre d'actes ; rule_keys null = toutes les garanties,
 * une = propre à une garantie, plusieurs = plafond PARTAGÉ.
 */
class CoverageLimit extends Model implements Auditable
{
    use AuditableTrait;

    protected $guarded = [];
    protected $casts = ['rule_keys' => 'array', 'amount_minor' => 'integer', 'max_count' => 'integer', 'active' => 'boolean'];

    public const SCOPES = ['act' => 'par acte', 'day' => 'par jour', 'month' => 'par mois', 'year' => 'par année civile'];
    public const HOLDERS = ['beneficiary' => 'par bénéficiaire', 'family' => 'par famille (adhérent et ayants droit)'];

    public function version() { return $this->belongsTo(GuaranteeVersion::class, 'guarantee_version_id'); }

    public function appliesTo(CoverageRule $rule): bool
    {
        return $this->rule_keys === null || $this->rule_keys === [] || in_array($rule->rule_key, $this->rule_keys, true);
    }
}
