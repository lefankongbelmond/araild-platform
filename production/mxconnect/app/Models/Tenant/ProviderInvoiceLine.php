<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

/** Ligne d'une facture de prestataire : une prestation validée (phase 5, 2026-11-26). */
class ProviderInvoiceLine extends Model
{
    protected $guarded = [];
    protected $casts = ['amount_minor' => 'integer', 'care_date' => 'date', 'decided_at' => 'datetime'];

    public const STATUSES = ['pending' => 'À contrôler', 'accepted' => 'Acceptée', 'rejected' => 'Rejetée'];

    /** Une ligne « active » empêche de refacturer la prestation ; une ligne rejetée la libère. */
    public const ACTIVE = ['pending', 'accepted'];

    public function invoice() { return $this->belongsTo(ProviderInvoice::class, 'provider_invoice_id'); }
    public function prestation() { return $this->belongsTo(Prestation::class)->withTrashed(); }
}
