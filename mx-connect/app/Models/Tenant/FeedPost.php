<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class FeedPost extends Model implements Auditable
{
    use AuditableTrait;

    protected $guarded = [];
    protected $casts = ['likes_count' => 'integer', 'comments_count' => 'integer'];

    public function member()   { return $this->belongsTo(Member::class); }
    public function comments() { return $this->hasMany(FeedComment::class); }
    public function likes()    { return $this->hasMany(FeedPostLike::class); }

    public function likedBy(int $memberId): bool
    {
        return $this->likes()->where('member_id', $memberId)->exists();
    }
}
