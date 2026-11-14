<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Demande d'ACCORD PRÉALABLE (2026-11-19) : demandée par un prestataire (ou la mutuelle), contrôlée
 * automatiquement (droits, garantie, plafond), examinée par une AUTRE personne (administrative ou
 * médicale selon la garantie), accordée avec un numéro ou rejetée avec un motif. Une urgence soignée
 * sans accord crée une demande « urgence » à régulariser a posteriori : les soins ne sont jamais bloqués.
 * clinical_info est une donnée de santé : visible seulement avec capacite.donnees-sante.
 */
class Preauthorization extends Model implements Auditable
{
    use AuditableTrait;

    protected $guarded = [];
    protected $casts = [
        'planned_on' => 'date', 'valid_until' => 'date', 'decided_at' => 'datetime', 'emergency' => 'boolean',
        'auto_checks' => 'array', 'estimated_minor' => 'integer', 'estimated_mutual_minor' => 'integer', 'approved_minor' => 'integer',
    ];
    protected $auditExclude = ['clinical_info'];

    public const STATUSES = [
        'requested' => 'À examiner', 'approved' => 'Accordée', 'rejected' => 'Rejetée',
        'used' => 'Utilisée', 'expired' => 'Expirée', 'cancelled' => 'Annulée',
    ];
    public const REVIEWS = ['administrative' => 'Examen administratif', 'medical' => 'Examen médical (médecin-conseil)'];

    public function member() { return $this->belongsTo(Member::class); }
    public function dependent() { return $this->belongsTo(Dependent::class); }
    public function actType() { return $this->belongsTo(ActType::class); }
    public function rule() { return $this->belongsTo(CoverageRule::class, 'coverage_rule_id'); }
    public function prestation() { return $this->belongsTo(Prestation::class); }

    /** Accord utilisable pour des soins à cette date (accordé, non utilisé, dans sa validité). */
    public function usableOn(string $date): bool
    {
        return $this->status === 'approved' && ! $this->prestation_id
            && (! $this->valid_until || $date <= $this->valid_until->toDateString());
    }
}
