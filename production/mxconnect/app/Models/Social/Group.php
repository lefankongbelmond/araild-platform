<?php

namespace App\Models\Social;

use App\Models\Central\Mutual;
use App\Models\Central\PublicAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Groupe communautaire animé et modéré par une mutuelle. Public : publications
 * visibles par tous, adhésion libre. Privé : publications visibles uniquement par
 * les membres, adhésion soumise à l'approbation de la mutuelle.
 */
class Group extends Model
{
    protected $connection = 'central';
    protected $table = 'social_groups';
    protected $guarded = [];
    protected $casts = ['is_demo' => 'boolean'];

    public function mutual()  { return $this->belongsTo(Mutual::class, 'mutual_id'); }
    public function members() { return $this->hasMany(GroupMember::class, 'group_id'); }
    public function posts()   { return $this->hasMany(Post::class, 'group_id'); }

    public function getRouteKeyName(): string { return 'slug'; }
    public function isPrivate(): bool { return $this->visibility === 'private'; }
    public function coverUrl(): ?string { return \App\Services\Social\MediaService::url($this->cover_path); }

    public function membership(?PublicAccount $account): ?GroupMember
    {
        return $account ? GroupMember::where('group_id', $this->id)->where('public_account_id', $account->id)->first() : null;
    }

    public function isMember(?PublicAccount $account): bool
    {
        return $this->membership($account)?->status === 'active';
    }

    /** Les publications de ce groupe sont-elles lisibles par ce visiteur ? */
    public function canRead(?PublicAccount $account): bool
    {
        return ! $this->isPrivate() || $this->isMember($account);
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::limit(Str::slug($name), 70, '') ?: 'groupe';
        $slug = $base; $i = 2;
        while (static::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    public function refreshCounters(): void
    {
        $this->forceFill([
            'members_count' => GroupMember::where('group_id', $this->id)->where('status', 'active')->count(),
            'posts_count'   => Post::where('group_id', $this->id)->where('status', 'published')->count(),
        ])->save();
    }
}
