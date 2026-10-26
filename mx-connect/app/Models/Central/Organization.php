<?php

namespace App\Models\Central;

use App\Enums\OrganizationManagerRole;
use App\Enums\OrganizationStatus;
use App\Enums\OrganizationType;
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

    public function isPublished(): bool
    {
        return $this->status === OrganizationStatus::Published;
    }

    /** Role of the given account on this organization's profile, or null if none. */
    public function roleOf(?PublicAccount $account): ?OrganizationManagerRole
    {
        if (! $account) {
            return null;
        }

        $role = $this->managers()->whereKey($account->getKey())->value('organization_managers.role');

        return $role ? OrganizationManagerRole::from($role) : null;
    }

    /** Owners/admins administer the profile and answer requests; editors may only publish. */
    public function isAdministeredBy(?PublicAccount $account): bool
    {
        return in_array($this->roleOf($account), [OrganizationManagerRole::Owner, OrganizationManagerRole::Admin], true);
    }

    public function canPublishAs(?PublicAccount $account): bool
    {
        return $this->roleOf($account) !== null;
    }

    /** Draft or suspended profiles are only visible to their own managers. */
    public function isVisibleTo(?PublicAccount $account): bool
    {
        return $this->isPublished() || $this->roleOf($account) !== null;
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', OrganizationStatus::Published->value);
    }

    /**
     * Domains are stored as ASCII slugs from config('mxconnect.social.domains'); callers
     * validate $domain against that list, so matching the quoted JSON token is exact
     * enough and portable across MySQL and SQLite.
     */
    public function scopeInDomain(Builder $query, string $domain): Builder
    {
        return $query->where('domains', 'like', '%"'.$domain.'"%');
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null;
    }

    public function initials(): string
    {
        $words = preg_split('/\s+/u', trim($this->name)) ?: [];

        return mb_strtoupper(collect($words)->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->join(''));
    }

    public function location(): string
    {
        return collect([$this->city, $this->region])->filter()->join(', ');
    }
}
