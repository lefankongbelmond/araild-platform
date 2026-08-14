<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class FeedComment extends Model
{
    protected $guarded = [];

    public function post()   { return $this->belongsTo(FeedPost::class, 'feed_post_id'); }
    public function member() { return $this->belongsTo(Member::class); }
}
