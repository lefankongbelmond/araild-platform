<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Grand-public identity/login (PWA members). A person may belong to several mutuals
 * via member_links; this is their single cross-mutual account.
 */
class PublicAccount extends Authenticatable
{
    use HasApiTokens;

    protected $connection = 'central';
    protected $guarded = [];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = ['password' => 'hashed', 'phone_verified_at' => 'datetime'];

    public function links() { return $this->hasMany(MemberLink::class); }

    /** Social-network layer (Lot 1 foundations — see Organization/Post models). */
    public function managedOrganizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_managers')
            ->withPivot('role')->withTimestamps();
    }

    public function follows(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_follows')->withTimestamps();
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PostComment::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(PostLike::class);
    }

    public function engagementRequests(): HasMany
    {
        return $this->hasMany(EngagementRequest::class);
    }

    public function supportOffers(): HasMany
    {
        return $this->hasMany(SupportOffer::class);
    }
}
