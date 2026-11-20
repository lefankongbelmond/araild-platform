<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

/** Historique d'une facture de prestataire (phase 5, 2026-11-26). */
class InvoiceEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];
    protected $casts = ['amount_minor' => 'integer'];

    public const LABELS = [
        'sent'     => 'Facture transmise à la mutuelle',
        'document' => 'Facture PDF jointe',
        'checked'  => 'Contrôle terminé par la mutuelle',
        'rejected' => 'Facture rejetée',
        'paid'     => 'Paiement effectué par la mutuelle',
    ];
}
