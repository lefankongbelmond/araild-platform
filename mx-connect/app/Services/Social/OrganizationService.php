<?php

namespace App\Services\Social;

use App\Enums\OrganizationManagerRole;
use App\Enums\OrganizationStatus;
use App\Models\Central\Organization;
use App\Models\Central\PublicAccount;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Public organization profiles. A profile is created by a public account (who becomes
 * its owner), published unverified: `verified_at` is never set from here — only a
 * network operator may verify an organization.
 */
class OrganizationService
{
    private const EDITABLE = [
        'type', 'name', 'tagline', 'description', 'country_id', 'region', 'city',
        'email', 'phone', 'website', 'socials', 'domains', 'stats',
    ];

    public function create(PublicAccount $owner, array $data, ?UploadedFile $logo = null, ?UploadedFile $cover = null): Organization
    {
        return DB::connection('central')->transaction(function () use ($owner, $data, $logo, $cover) {
            $org = Organization::create([
                ...$this->editable($data),
                'slug' => $this->uniqueSlug($data['name']),
                'status' => OrganizationStatus::Published,
                'verified_at' => null,
                'verified_by' => null,
                'mutual_id' => null,
                'followers_count' => 0,
                'logo_path' => $logo?->store('organizations/logos', 'public'),
                'cover_path' => $cover?->store('organizations/covers', 'public'),
            ]);

            $org->managers()->attach($owner->getKey(), ['role' => OrganizationManagerRole::Owner->value]);

            return $org->refresh();
        });
    }

    public function update(Organization $org, PublicAccount $actor, array $data, ?UploadedFile $logo = null, ?UploadedFile $cover = null): Organization
    {
        $this->authorizeAdmin($org, $actor);

        $attributes = $this->editable($data);
        if ($logo) {
            $this->deleteFile($org->logo_path);
            $attributes['logo_path'] = $logo->store('organizations/logos', 'public');
        }
        if ($cover) {
            $this->deleteFile($org->cover_path);
            $attributes['cover_path'] = $cover->store('organizations/covers', 'public');
        }

        $org->update($attributes);

        return $org->refresh();
    }

    /** @return bool true when a new follow was recorded */
    public function follow(Organization $org, PublicAccount $account): bool
    {
        if (! $org->isPublished()) {
            throw ValidationException::withMessages(['organization' => __('mxconnect.social.errors.org_unavailable')]);
        }

        try {
            return DB::connection('central')->transaction(function () use ($org, $account) {
                $attached = $org->followers()->syncWithoutDetaching([$account->getKey()])['attached'];
                if ($attached === []) {
                    return false;
                }
                Organization::whereKey($org->getKey())->increment('followers_count');

                return true;
            });
        } catch (UniqueConstraintViolationException) {
            return false; // concurrent duplicate click — already following
        }
    }

    public function unfollow(Organization $org, PublicAccount $account): bool
    {
        return DB::connection('central')->transaction(function () use ($org, $account) {
            if ($org->followers()->detach($account->getKey()) === 0) {
                return false;
            }
            Organization::whereKey($org->getKey())->where('followers_count', '>', 0)->decrement('followers_count');

            return true;
        });
    }

    public function authorizeAdmin(Organization $org, PublicAccount $actor): void
    {
        if (! $org->isAdministeredBy($actor)) {
            throw new AuthorizationException(__('mxconnect.social.errors.not_admin'));
        }
    }

    private function editable(array $data): array
    {
        $attributes = array_intersect_key($data, array_flip(self::EDITABLE));

        if (array_key_exists('domains', $attributes)) {
            $allowed = config('mxconnect.social.domains');
            $attributes['domains'] = array_values(array_intersect($allowed, (array) $attributes['domains']));
        }
        if (array_key_exists('stats', $attributes)) {
            $attributes['stats'] = collect((array) $attributes['stats'])
                ->filter(fn ($row) => filled($row['label'] ?? null) && filled($row['value'] ?? null))
                ->take(4)
                ->mapWithKeys(fn ($row) => [trim($row['label']) => trim($row['value'])])
                ->all();
        }
        if (array_key_exists('socials', $attributes)) {
            $attributes['socials'] = array_filter((array) $attributes['socials']);
        }

        return $attributes;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'organisation';
        $base = Str::limit($base, 70, '');
        if (in_array($base, config('mxconnect.social.reserved_slugs'), true)) {
            $base .= '-org';
        }

        $slug = $base;
        $i = 2;
        while (Organization::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    private function deleteFile(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
