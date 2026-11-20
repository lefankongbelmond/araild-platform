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
    protected $casts = ['amount_minor' => 'integer', 'billed_minor' => 'integer', 'provider_id' => 'integer', 'decided_at' => 'datetime',
        'checked_at' => 'datetime', 'paid_on' => 'date'];

    /**
     * Libellés des états (registre des factures, portail prestataire). Phase 5 (2026-11-26) :
     * « validée » (contrôlée, à payer) et « payée » (paiement effectué, avec sa référence) distincts.
     */
    public const STATUSES = ['captured' => 'À contrôler', 'processed' => 'Validée, à payer', 'prepared' => 'Mise en paiement', 'paid' => 'Payée', 'rejected' => 'Rejetée'];
    public const PAYABLE = ['captured', 'processed', 'prepared'];

    public function careClaim() { return $this->belongsTo(CareClaim::class); }
    public function lines() { return $this->hasMany(ProviderInvoiceLine::class); }
    public function events() { return $this->hasMany(InvoiceEvent::class)->orderBy('id'); }

    /** Facture à lignes (phase 5) ? Les anciennes factures n'en ont pas. */
    public function hasLines(): bool
    {
        if (! \App\Services\Tenant\ProviderBillingService::ready()) {
            return false;
        }

        return $this->relationLoaded('lines') ? $this->lines->isNotEmpty() : $this->lines()->exists();
    }

    /** Libellé affiché (référence du bordereau, sinon numéro). */
    public function label(): string
    {
        return $this->reference ?: 'n° ' . $this->id;
    }
}
