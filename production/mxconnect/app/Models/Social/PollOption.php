<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;

class PollOption extends Model
{
    protected $connection = 'central';
    protected $table = 'social_poll_options';
    protected $guarded = [];
    public $timestamps = false;
}
