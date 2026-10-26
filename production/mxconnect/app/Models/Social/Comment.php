<?php

namespace App\Models\Social;

use App\Models\Central\Mutual;
use App\Models\Central\PublicAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends Model
{
    use SoftDeletes;

    protected $connection = 'central';
    protected $table = 'social_comments';
    protected $guarded = [];

    public function post()    { return $this->belongsTo(Post::class, 'post_id'); }
    public function account() { return $this->belongsTo(PublicAccount::class, 'public_account_id'); }
    public function mutual()  { return $this->belongsTo(Mutual::class, 'mutual_id'); }
    public function mentions() { return $this->hasMany(Mention::class, 'comment_id'); }
    public function replies() { return $this->hasMany(Comment::class, 'parent_id')->where('status', 'visible')->orderBy('created_at'); }

    public function authorName(): string
    {
        if ($this->mutual_id) {
            return $this->mutual?->name ?? 'Mutuelle';
        }

        return $this->account ? trim($this->account->first_name . ' ' . $this->account->last_name) : ($this->author_label ?: 'Membre');
    }
}
