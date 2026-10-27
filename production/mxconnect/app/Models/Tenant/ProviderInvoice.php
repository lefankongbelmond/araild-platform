<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class ProviderInvoice extends Model implements Auditable
{
    use SoftDeletes, AuditableTrait;

    protected $guarded = [];
    protected $casts = ['amount_minor' => 'integer', 'provider_id' => 'integer', 'decided_at' => 'datetime'];

    /** Libellés des états (registre des factures, portail prestataire). */
    public const STATUSES = ['captured' => 'À vérifier', 'processed' => 'En traitement', 'prepared' => 'Mise en paiement', 'paid' => 'Payée', 'rejected' => 'Rejetée'];
    public const PAYABLE = ['captured', 'processed', 'prepared'];

    public function careClaim() { return $this->belongsTo(CareClaim::class); }
}
