<?php

namespace App\Models\Tenant;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Subscription extends Model implements Auditable
{
    use SoftDeletes, AuditableTrait;

    protected $guarded = [];
    protected $casts = [
        'effective_date' => 'date',
        'ends_on' => 'date',          // fin après un changement de formule (2026-11-23)
        'waiting_from' => 'date',     // ancienneté conservée pour les carences
        'observation_ends_on' => 'date',
        'validated_at' => 'datetime',
        'status' => SubscriptionStatus::class,
    ];

    public function member()          { return $this->belongsTo(Member::class); }
    public function guaranteeVersion(){ return $this->belongsTo(GuaranteeVersion::class); }
    public function schedules()       { return $this->hasMany(ContributionSchedule::class, 'subscription_id'); }
    public function migratedFrom()    { return $this->belongsTo(self::class, 'migrated_from_id'); }
}
