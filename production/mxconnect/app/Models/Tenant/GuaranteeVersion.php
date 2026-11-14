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
        'renewal_waiver' => 'boolean',
    ];

    public function guarantee() { return $this->belongsTo(Guarantee::class); }
    public function ceilings()  { return $this->hasMany(Ceiling::class); }
    public function rules()     { return $this->hasMany(CoverageRule::class)->orderBy('sort')->orderBy('id'); }
    public function limits()    { return $this->hasMany(CoverageLimit::class); }
    public function waitingPeriods() { return $this->hasMany(WaitingPeriod::class); }

    /**
     * Version gérée par le catalogue (garanties détaillées) ? Sinon : ancien fonctionnement
     * (taux unique, plafonds « ceilings », période d'observation), inchangé.
     */
    public function hasCatalog(): bool
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('coverage_rules')) {
            return false;
        }

        return $this->relationLoaded('rules') ? $this->rules->isNotEmpty() : CoverageRule::where('guarantee_version_id', $this->id)->exists();
    }

    /** The version whose [valid_from, valid_to] contains the given care date. */
    public static function applicableOn(int $guaranteeId, string $careDate): ?self
    {
        return static::where('guarantee_id', $guaranteeId)
            ->whereDate('valid_from', '<=', $careDate)
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $careDate))
            ->first();
    }
}
