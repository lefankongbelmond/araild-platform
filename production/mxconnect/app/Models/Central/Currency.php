<?php

namespace App\Models\Central;

use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model implements Auditable
{
    use AuditableTrait;

    protected $connection = 'central';
    protected $guarded = [];
    protected $casts = ['active' => 'boolean', 'minor_unit' => 'integer'];

    /** Convert a major-unit amount to integer minor units for storage. */
    public function toMinor(float $major): int
    {
        return (int) round($major * (10 ** $this->minor_unit));
    }

    /** Format integer minor units back to a display string. */
    public function format(int $minor): string
    {
        $major = $minor / (10 ** $this->minor_unit);
        // Séparateurs selon la langue de l'interface (2026-11-27) ; le montant et la devise ne
        // changent jamais (aucune conversion). Français : « 51 600 FCFA », comme avant.
        [$dec, $thousands] = \App\Support\Locales::separators();

        return number_format($major, $this->minor_unit, $dec, $thousands) . ' ' . $this->symbol;
    }
}
