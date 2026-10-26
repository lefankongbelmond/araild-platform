<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;

/** Notification affichée dans la cloche d'un compte membre. */
class SocialNotification extends Model
{
    protected $connection = 'central';
    protected $table = 'social_notifications';
    protected $guarded = [];
    protected $casts = ['read_at' => 'datetime'];
}
