<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;

/** Denormalised public offer summary for the comparator (central). */
class MutualOffer extends Model
{
    protected $connection = 'central';
    protected $guarded = [];
    protected $casts = [
        'highlights'  => 'array',
        'published'   => 'boolean',
        'synced_at'   => 'datetime',
        'coverage_min' => 'float',
        'coverage_max' => 'float',
    ];

    public function mutual()   { return $this->belongsTo(Mutual::class); }
    public function currency() { return $this->belongsTo(Currency::class); }
}
