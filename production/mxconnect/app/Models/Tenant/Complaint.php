<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Complaint extends Model implements Auditable
{
    use AuditableTrait;

    protected $guarded = [];
    protected $casts = [
        'received_on' => 'date',
        'due_on'      => 'date',
        'resolved_on' => 'date',
    ];

    public function member() { return $this->belongsTo(Member::class); }
    public function messages() { return $this->hasMany(ComplaintMessage::class)->orderBy('id'); }

    /** Catégories d'une demande faite par l'adhérent depuis son portail (2026-11-20). */
    public const CATEGORIES = ['complaint' => 'Réclamation', 'provider_issue' => 'Problème chez un prestataire', 'information' => 'Demande d’information'];
    public const STATUS_LABELS = ['received' => 'Reçue', 'in_progress' => 'En cours de traitement', 'resolved' => 'Traitée', 'closed' => 'Clôturée'];

    /** True when past the SLA due date and not yet resolved/closed. */
    public function isOverdue(): bool
    {
        return $this->due_on
            && ! in_array($this->status, ['resolved', 'closed'], true)
            && $this->due_on->isPast();
    }
}
