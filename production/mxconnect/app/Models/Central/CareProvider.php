<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class CareProvider extends Model implements Auditable
{
    use AuditableTrait;

    protected $connection = 'central';
    protected $guarded = [];
    protected $casts = [
        'active' => 'boolean', 'approval_expires_on' => 'date', 'specialties' => 'array', 'opening_hours' => 'array',
        'bank_details' => 'encrypted:array', 'latitude' => 'float', 'longitude' => 'float',
    ];
    protected $hidden = ['bank_details'];
    // Coordonnées bancaires : jamais recopiées en clair dans le journal d'audit.
    protected $auditExclude = ['bank_details'];

    /** Types de structure (même liste que les conventions des mutuelles). */
    public const TYPES = \App\Models\Tenant\ProviderConvention::TYPES;

    public function country() { return $this->belongsTo(Country::class); }

    public function memberships() { return $this->hasMany(\App\Models\Provider\ProviderMembership::class); }
    public function affiliations() { return $this->hasMany(\App\Models\Provider\ProviderAffiliation::class); }
    public function documents() { return $this->hasMany(\App\Models\Provider\ProviderDocument::class); }

    public function isPending(): bool
    {
        return ($this->registration_status ?? 'approved') === 'pending';
    }
}
