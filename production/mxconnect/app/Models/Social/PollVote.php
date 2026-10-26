<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;

/** Vote : sert seulement à empêcher le double vote ; jamais affiché nominativement. */
class PollVote extends Model
{
    protected $connection = 'central';
    protected $table = 'social_poll_votes';
    protected $guarded = [];
}
