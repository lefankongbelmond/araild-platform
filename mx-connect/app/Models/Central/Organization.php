<?php

namespace App\Models\Central;

use App\Enums\OrganizationStatus;
use App\Enums\OrganizationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Public, cross-mutual organization profile (the social-network layer). Optionally
 * linked to a `Mutual` tenant via `mutual_id`, but a mutual is not required — NGOs,
 * associations, care providers and partners get a profile here too.
 */
class Organization extends Model implements Auditable
{
    use AuditableTrait, SoftDeletes;

    protected $connection = 'central';
    protected $guarded = [];

    protected $casts = [
        'type' => OrganizationType::class,
        'status' => OrganizationStatus::class,
        'socials' => 'array',
        'domains' => 'array',
        'stats' => 'array',
        'verified_at' => 'datetime',
    ];

    public function mutual(): BelongsTo
    {
        return $this->belongsTo(Mutual::class, 'mutual_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(NetworkUser::class, 'verified_by');
    }

    public function managers(): BelongsToMany
    {
        return $this->belongsToMany(PublicAccount::class, 'organization_managers')
            ->withPivot('role')->withTimestamps();
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(PublicAccount::class, 'organization_follows')->withTimestamps();
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function engagementRequests(): HasMany
    {
        return $this->hasMany(EngagementRequest::class);
    }

    public function supportOffers(): HasMany
    {
        return $this->hasMany(SupportOffer::class);
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }
}
