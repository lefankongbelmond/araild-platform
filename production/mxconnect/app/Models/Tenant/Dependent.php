<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Dependent extends Model
{
    use SoftDeletes;
    protected $guarded = [];
    protected $casts = ['birth_date' => 'date'];

    public function member() { return $this->belongsTo(Member::class); }
}
