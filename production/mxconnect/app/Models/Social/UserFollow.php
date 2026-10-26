<?php

namespace App\Models\Social;

use App\Models\Central\PublicAccount;
use Illuminate\Database\Eloquent\Model;

/** Abonnement d'un membre au compte d'un autre membre. */
class UserFollow extends Model
{
    protected $connection = 'central';
    protected $table = 'social_user_follows';
    protected $guarded = [];

    public function follower() { return $this->belongsTo(PublicAccount::class, 'follower_id'); }
    public function followed() { return $this->belongsTo(PublicAccount::class, 'followed_id'); }
}
