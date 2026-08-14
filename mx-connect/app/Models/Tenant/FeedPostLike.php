<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class FeedPostLike extends Model
{
    protected $guarded = [];

    public function post()   { return $this->belongsTo(FeedPost::class, 'feed_post_id'); }
    public function member() { return $this->belongsTo(Member::class); }
}
