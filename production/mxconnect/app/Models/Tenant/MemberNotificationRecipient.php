<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class MemberNotificationRecipient extends Model
{
    protected $guarded = [];
    protected $casts = ['in_app' => 'boolean'];

    public function member() { return $this->belongsTo(Member::class); }
}
