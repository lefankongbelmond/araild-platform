<?php

namespace App\Models\Central;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Grand-public identity/login (PWA members). A person may belong to several mutuals
 * via member_links; this is their single cross-mutual account.
 */
class PublicAccount extends Authenticatable
{
    use HasApiTokens;

    protected $connection = 'central';
    protected $guarded = [];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = ['password' => 'hashed', 'phone_verified_at' => 'datetime'];

    public function links() { return $this->hasMany(MemberLink::class); }
}
