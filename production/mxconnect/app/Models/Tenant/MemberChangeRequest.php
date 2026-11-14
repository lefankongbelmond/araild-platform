<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Changement demandé par l'adhérent depuis son portail (2026-11-20) : coordonnées, ajout,
 * modification ou retrait d'un ayant droit. Rien n'est appliqué avant la validation par la mutuelle ;
 * un ayant droit ajouté n'est couvert qu'à partir de sa date d'intégration (validation).
 */
class MemberChangeRequest extends Model implements Auditable
{
    use AuditableTrait;

    protected $guarded = [];
    protected $casts = ['payload' => 'array', 'decided_at' => 'datetime'];

    public const KINDS = [
        'contact' => 'Mise à jour des coordonnées', 'dependent_add' => 'Ajout d’un bénéficiaire',
        'dependent_update' => 'Modification d’un bénéficiaire', 'dependent_remove' => 'Retrait d’un bénéficiaire',
    ];
    public const STATUSES = ['requested' => 'En attente', 'approved' => 'Acceptée', 'rejected' => 'Refusée', 'cancelled' => 'Annulée'];

    public function member() { return $this->belongsTo(Member::class); }
    public function dependent() { return $this->belongsTo(Dependent::class); }
}
