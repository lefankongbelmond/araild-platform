<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;

/** Organisation masquée du flux personnalisé (son profil reste consultable). */
class Mute extends Model
{
    protected $connection = 'central';
    protected $table = 'social_mutes';
    protected $guarded = [];
}
