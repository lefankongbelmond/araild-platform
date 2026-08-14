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
    protected $casts = ['active' => 'boolean'];

    public function country() { return $this->belongsTo(Country::class); }
}
