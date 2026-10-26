<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MemberGroup extends Model
{
    use SoftDeletes;
    protected $guarded = [];
    public function members() { return $this->hasMany(Member::class); }
}
