<?php

namespace App\Models\Provider;

use App\Models\Central\CareProvider;
use App\Models\Central\Mutual;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Lien établissement ↔ mutuelle (index CENTRAL). La convention elle-même (n°, dates, délais)
 * reste dans la base de la mutuelle (provider_conventions) : c'est elle qui fait foi.
 */
class ProviderAffiliation extends Model implements Auditable
{
    use AuditableTrait;

    protected $connection = 'central';
    protected $guarded = [];
    protected $casts = ['history' => 'array', 'decided_at' => 'datetime'];

    public const STATUSES = [
        'requested' => 'Demande envoyée', 'approved' => 'Convention en place', 'suspended' => 'Convention suspendue',
        'rejected' => 'Demande refusée', 'withdrawn' => 'Demande retirée',
    ];

    public function provider() { return $this->belongsTo(CareProvider::class, 'care_provider_id'); }
    public function mutual() { return $this->belongsTo(Mutual::class, 'mutual_id'); }

    /** Ajoute une ligne à l'historique (qui, quoi, quand). */
    public function log(string $event, ?string $who = null, ?string $note = null): void
    {
        $h = (array) ($this->history ?? []);
        $h[] = array_filter(['at' => now()->toIso8601String(), 'event' => $event, 'by' => $who, 'note' => $note]);
        $this->history = array_slice($h, -50);
    }
}
