<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;

class Reaction extends Model
{
    protected $connection = 'central';
    protected $table = 'social_reactions';
    protected $guarded = [];
}
