<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Dérogation (prise en charge exceptionnelle) : demandée par une personne, décidée par une AUTRE
 * (« 4 yeux »). Elle ne modifie jamais la règle : le moteur l'applique en plus, et la prestation
 * qui l'utilise en garde la trace (prestations.coverage_exception_id).
 */
class CoverageException extends Model implements Auditable
{
    use AuditableTrait;

    protected $guarded = [];
    protected $casts = ['valid_from' => 'date', 'valid_to' => 'date', 'decided_at' => 'datetime', 'amount_minor' => 'integer', 'used_minor' => 'integer'];

    public const KINDS = [
        'waiting_waiver' => 'Dispense de délai de carence',
        'limit_extra' => 'Plafond supplémentaire (montant)',
        'exclusion_override' => 'Prise en charge d’un acte exclu',
    ];
    public const STATUSES = ['requested' => 'À décider', 'approved' => 'Accordée', 'rejected' => 'Refusée', 'revoked' => 'Retirée'];

    public function member() { return $this->belongsTo(Member::class); }
    public function dependent() { return $this->belongsTo(Dependent::class); }
    public function category() { return $this->belongsTo(ServiceCategory::class, 'service_category_id'); }
    public function actType() { return $this->belongsTo(ActType::class); }

    /** Reste disponible d'un plafond supplémentaire. */
    public function remainingMinor(): int
    {
        return max(0, (int) $this->amount_minor - (int) $this->used_minor);
    }
}
