<?php

namespace App\Models\Provider;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/** Agrément, convention ou pièce administrative d'un établissement (PDF, disque privé). */
class ProviderDocument extends Model implements Auditable
{
    use AuditableTrait, SoftDeletes;

    protected $connection = 'central';
    protected $guarded = [];
    protected $casts = ['issued_on' => 'date', 'expires_on' => 'date', 'size' => 'integer'];

    public const KINDS = [
        'approval' => 'Agrément / autorisation d’exercer', 'convention' => 'Convention avec une mutuelle',
        'insurance' => 'Assurance responsabilité civile', 'registration' => 'Registre de commerce / statuts',
        'tax' => 'Attestation fiscale', 'bank' => 'Relevé d’identité bancaire', 'other' => 'Autre document',
    ];

    public function expiresSoon(int $days = 60): bool
    {
        return $this->expires_on && $this->expires_on->lte(now()->addDays($days));
    }
}
