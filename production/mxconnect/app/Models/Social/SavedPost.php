<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;

class SavedPost extends Model
{
    protected $connection = 'central';
    protected $table = 'social_saved_posts';
    protected $guarded = [];

    public function post() { return $this->belongsTo(Post::class, 'post_id'); }
}
