<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class GuaranteeVersion extends Model
{
    protected $guarded = [];
    protected $casts = [
        'valid_from' => 'date',
        'valid_to'   => 'date',
        'exclusions' => 'array',
        'provider_network' => 'array',
        'locked' => 'boolean',
    ];

    public function guarantee() { return $this->belongsTo(Guarantee::class); }
    public function ceilings()  { return $this->hasMany(Ceiling::class); }

    /** The version whose [valid_from, valid_to] contains the given care date. */
    public static function applicableOn(int $guaranteeId, string $careDate): ?self
    {
        return static::where('guarantee_id', $guaranteeId)
            ->whereDate('valid_from', '<=', $careDate)
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $careDate))
            ->first();
    }
}
