<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Guarantee extends Model
{
    use SoftDeletes;
    protected $guarded = [];
    public function versions() { return $this->hasMany(GuaranteeVersion::class); }
    public function currentVersion() { return $this->hasOne(GuaranteeVersion::class)->whereNull('valid_to'); }
}
