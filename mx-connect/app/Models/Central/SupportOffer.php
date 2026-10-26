<?php

namespace App\Models\Central;

use App\Enums\EngagementStatus;
use App\Enums\SupportType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/** Action C — "Soutenir ou devenir partenaire" proposals. Never a payment: an intent only. */
class SupportOffer extends Model implements Auditable
{
    use AuditableTrait, SoftDeletes;

    protected $connection = 'central';
    protected $guarded = [];

    protected $casts = [
        'support_type' => SupportType::class,
        'status' => EngagementStatus::class,
        'contact_info' => 'array',
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
