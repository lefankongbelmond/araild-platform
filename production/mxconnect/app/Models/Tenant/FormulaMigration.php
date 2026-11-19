<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/** Changement de formule d'un groupe d'adhérents, demandé puis validé par une autre personne (2026-11-23). */
class FormulaMigration extends Model implements Auditable
{
    use AuditableTrait;

    protected $guarded = [];

    protected $casts = [
        'effective_date' => 'date',
        'keep_seniority' => 'boolean',
        'member_ids' => 'array',
        'result' => 'array',
        'decided_at' => 'datetime',
        'applied_at' => 'datetime',
    ];

    public const STATUSES = [
        'requested' => 'À valider', 'approved' => 'Validée — à appliquer à la date d’effet', 'rejected' => 'Refusée',
        'applied' => 'Appliquée', 'cancelled' => 'Retirée',
    ];

    public function source() { return $this->belongsTo(Guarantee::class, 'source_guarantee_id')->withTrashed(); }
    public function target() { return $this->belongsTo(Guarantee::class, 'target_guarantee_id')->withTrashed(); }
}
