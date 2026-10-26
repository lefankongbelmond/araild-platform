<?php

namespace App\Models\Social;

use App\Models\Central\Mutual;
use App\Models\Central\PublicAccount;
use Illuminate\Database\Eloquent\Model;

/** Conversation privée entre un membre et une mutuelle. */
class Conversation extends Model
{
    protected $connection = 'central';
    protected $table = 'social_conversations';
    protected $guarded = [];
    protected $casts = ['last_message_at' => 'datetime'];

    public function mutual()   { return $this->belongsTo(Mutual::class, 'mutual_id'); }
    public function account()  { return $this->belongsTo(PublicAccount::class, 'public_account_id'); }
    public function messages() { return $this->hasMany(Message::class, 'conversation_id')->orderBy('id'); }
    public function adhesion() { return $this->belongsTo(AdhesionRequest::class, 'adhesion_request_id'); }
    public function lastMessage() { return $this->hasOne(Message::class, 'conversation_id')->latestOfMany(); }
}
