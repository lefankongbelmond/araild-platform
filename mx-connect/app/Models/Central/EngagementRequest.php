<?php

namespace App\Models\Central;

use App\Enums\ContributionType;
use App\Enums\EngagementStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/** Action A — "Je m'engage" requests, from a public account toward an organization. */
class EngagementRequest extends Model implements Auditable
{
    use AuditableTrait, SoftDeletes;

    protected $connection = 'central';
    protected $guarded = [];

    protected $casts = [
        'contribution_type' => ContributionType::class,
        'status' => EngagementStatus::class,
        'responded_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(PublicAccount::class, 'public_account_id');
    }

    public function respondedBy(): BelongsTo
    {
        return $this->belongsTo(PublicAccount::class, 'responded_by_account_id');
    }
}
