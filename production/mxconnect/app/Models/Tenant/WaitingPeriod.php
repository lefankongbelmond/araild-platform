<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/** Délai de carence : par garantie (rule_key, null = toutes) et par catégorie de bénéficiaire. */
class WaitingPeriod extends Model implements Auditable
{
    use AuditableTrait;

    protected $guarded = [];
    protected $casts = ['days' => 'integer'];

    public function version() { return $this->belongsTo(GuaranteeVersion::class, 'guarantee_version_id'); }
}
