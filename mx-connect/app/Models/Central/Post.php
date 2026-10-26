<?php

namespace App\Models\Central;

use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Enums\PostVisibility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * A public feed post, authored by EITHER an Organization OR an individual PublicAccount
 * (never both — enforced by the posting service, not the schema).
 */
class Post extends Model implements Auditable
{
    use AuditableTrait, SoftDeletes;

    protected $connection = 'central';
    protected $guarded = [];

    protected $casts = [
        'type' => PostType::class,
        'visibility' => PostVisibility::class,
        'status' => PostStatus::class,
        'media' => 'array',
        'poll_options' => 'array',
        'event_starts_at' => 'datetime',
        'poll_closes_at' => 'datetime',
        'pinned_at' => 'datetime',
        'published_at' => 'datetime',
        'event_online' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(PublicAccount::class, 'public_account_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function hashtags(): BelongsToMany
    {
        return $this->belongsToMany(Hashtag::class, 'post_hashtag');
    }

    public function likes(): HasMany
    {
        return $this->hasMany(PostLike::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PostComment::class);
    }

    public function engagementRequests(): HasMany
    {
        return $this->hasMany(EngagementRequest::class);
    }

    public function supportOffers(): HasMany
    {
        return $this->hasMany(SupportOffer::class);
    }

    public function likedBy(int $publicAccountId): bool
    {
        return $this->likes()->where('public_account_id', $publicAccountId)->exists();
    }
}
