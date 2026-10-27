<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;

/** Trace d'une acceptation, d'une prise de connaissance ou d'un consentement (jamais modifiée). */
class LegalAcceptance extends Model
{
    public const UPDATED_AT = null;

    protected $connection = 'central';
    protected $table = 'legal_acceptances';
    protected $guarded = [];

    public const ACTIONS = [
        'accepted'          => 'Accepté',
        'acknowledged'      => 'Pris connaissance',
        'consent_given'     => 'Consentement donné',
        'consent_withdrawn' => 'Consentement retiré',
    ];

    public function document() { return $this->belongsTo(LegalDocument::class, 'legal_document_id'); }
}
