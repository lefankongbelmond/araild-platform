<?php

namespace App\Models\Central;

use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Enums\PostVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
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

    /**
     * Server-side visibility rule, used by every read path (feed, permalink, likes,
     * comments): published only; an org-authored post needs its organization to be
     * published unless the viewer manages it; "followers" posts are limited to the
     * author, the organization's followers and its managers.
     */
    public function scopeVisibleTo(Builder $query, ?PublicAccount $viewer): Builder
    {
        $managed = $viewer ? $viewer->managedOrganizations()->pluck('organizations.id') : collect();
        $followed = $viewer ? $viewer->follows()->pluck('organizations.id') : collect();

        return $query
            ->where('posts.status', PostStatus::Published->value)
            ->where(function (Builder $q) use ($managed) {
                $q->whereNull('posts.organization_id')
                    ->orWhereHas('organization', fn (Builder $o) => $o->published())
                    ->orWhereIn('posts.organization_id', $managed);
            })
            ->where(function (Builder $q) use ($viewer, $managed, $followed) {
                $q->where('posts.visibility', PostVisibility::Public->value);
                if ($viewer) {
                    $q->orWhere('posts.public_account_id', $viewer->getKey())
                        ->orWhereIn('posts.organization_id', $managed->merge($followed));
                }
            });
    }

    public function isVisibleTo(?PublicAccount $viewer): bool
    {
        return static::query()->whereKey($this->getKey())->visibleTo($viewer)->exists();
    }

    /** "Je m'engage" only makes sense on an organization's call to action or project. */
    public function offersEngagement(): bool
    {
        return $this->organization_id !== null
            && ($this->project_id !== null || in_array($this->type, [PostType::Event, PostType::Opportunity], true));
    }

    /** "Soutenir ou devenir partenaire": opportunities, results and project posts. */
    public function offersSupport(): bool
    {
        return $this->organization_id !== null
            && ($this->project_id !== null || in_array($this->type, [PostType::Opportunity, PostType::Result], true));
    }

    /** @return list<array{url: string, type: string}> */
    public function mediaItems(): array
    {
        return collect($this->media ?? [])
            ->map(fn (array $m) => ['url' => Storage::disk('public')->url($m['path']), 'type' => $m['type']])
            ->all();
    }
}
