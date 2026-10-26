<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $connection = 'central';
    protected $table = 'social_messages';
    protected $guarded = [];

    public function conversation() { return $this->belongsTo(Conversation::class, 'conversation_id'); }
}
