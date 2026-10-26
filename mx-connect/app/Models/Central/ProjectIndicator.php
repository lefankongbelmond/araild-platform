<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A declared result, true until a network operator attaches evidence and sets
 * `is_verified` — never flip it to true without that check (see BUILD rules §5 Action B).
 */
class ProjectIndicator extends Model
{
    protected $connection = 'central';
    protected $guarded = [];

    protected $casts = [
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(NetworkUser::class, 'verified_by');
    }
}
