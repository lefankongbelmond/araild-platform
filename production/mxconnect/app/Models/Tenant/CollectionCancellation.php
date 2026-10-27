<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/** Demande d'annulation d'un encaissement de cotisation (2026-11-07) : motif, décision « 4 yeux ». */
class CollectionCancellation extends Model implements Auditable
{
    use AuditableTrait;

    protected $guarded = [];
    protected $casts = ['amount_minor' => 'integer', 'decided_at' => 'datetime'];

    public const STATUSES = ['pending' => 'À valider', 'approved' => 'Annulé', 'rejected' => 'Refusée'];

    public function member()      { return $this->belongsTo(Member::class); }
    public function requestedBy() { return $this->belongsTo(User::class, 'requested_by'); }
    public function decidedBy()   { return $this->belongsTo(User::class, 'decided_by'); }

    /** Lignes de paiement de l'encaissement (annulées comprises). */
    public function payments()
    {
        return ContributionPayment::withTrashed()->where('collection_ref', $this->collection_ref);
    }
}
