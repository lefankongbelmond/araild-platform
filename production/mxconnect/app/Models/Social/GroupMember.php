<?php

namespace App\Models\Social;

use App\Models\Central\PublicAccount;
use Illuminate\Database\Eloquent\Model;

class GroupMember extends Model
{
    protected $connection = 'central';
    protected $table = 'social_group_members';
    protected $guarded = [];

    public function group()   { return $this->belongsTo(Group::class, 'group_id'); }
    public function account() { return $this->belongsTo(PublicAccount::class, 'public_account_id'); }
}
