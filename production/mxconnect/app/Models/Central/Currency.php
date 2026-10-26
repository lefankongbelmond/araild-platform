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
        return number_format($major, $this->minor_unit, ',', ' ') . ' ' . $this->symbol;
    }
}
