<?php

namespace App\Models\Tenant;

use App\Models\Central\CareProvider;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

/**
 * Prestataire de soins conventionné par la mutuelle (base de la mutuelle). La fiche du
 * prestataire (nom, type, localité) est dans le registre CENTRAL care_providers.
 */
class ProviderConvention extends Model implements Auditable
{
    use AuditableTrait;

    protected $guarded = [];
    protected $casts = ['active' => 'boolean', 'starts_on' => 'date', 'ends_on' => 'date', 'care_provider_id' => 'integer'];

    public const TYPES = [
        'hospital' => 'Hôpital', 'clinic' => 'Clinique', 'health_center' => 'Centre de santé',
        'pharmacy' => 'Pharmacie', 'laboratory' => 'Laboratoire', 'other' => 'Autre',
    ];

    public function provider(): ?CareProvider
    {
        return CareProvider::on('central')->find($this->care_provider_id);
    }

    /** Convention en vigueur aujourd'hui (active, et dans ses dates si elles sont renseignées). */
    public function inForce(): bool
    {
        $today = now()->startOfDay();

        return $this->active
            && (! $this->starts_on || $this->starts_on->lte($today))
            && (! $this->ends_on || $this->ends_on->gte($today));
    }

    public static function forProvider(?int $providerId): ?self
    {
        return $providerId ? self::where('care_provider_id', $providerId)->first() : null;
    }
}
