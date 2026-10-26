<?php

namespace App\Models\Central;

use App\Enums\ProjectStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Project extends Model implements Auditable
{
    use AuditableTrait, SoftDeletes;

    protected $connection = 'central';
    protected $guarded = [];

    protected $casts = [
        'status' => ProjectStatus::class,
        'partners' => 'array',
        'documents' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'progress_percent' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function indicators(): HasMany
    {
        return $this->hasMany(ProjectIndicator::class);
    }

    public function updates(): HasMany
    {
        return $this->hasMany(ProjectUpdate::class);
    }

    public function engagementRequests(): HasMany
    {
        return $this->hasMany(EngagementRequest::class);
    }

    public function supportOffers(): HasMany
    {
        return $this->hasMany(SupportOffer::class);
    }

    /** A project inherits its organization's visibility (draft/suspended → managers only). */
    public function isVisibleTo(?PublicAccount $viewer): bool
    {
        return $this->organization->isVisibleTo($viewer);
    }

    public function scopeOfPublishedOrganizations(Builder $query): Builder
    {
        return $query->whereHas('organization', fn (Builder $o) => $o->published());
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null;
    }
}
