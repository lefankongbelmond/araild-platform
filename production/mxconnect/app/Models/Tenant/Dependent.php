<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Dependent extends Model
{
    use SoftDeletes;
    protected $guarded = [];
    protected $casts = ['birth_date' => 'date', 'joined_on' => 'date', 'left_on' => 'date', 'is_student' => 'boolean'];

    public function member() { return $this->belongsTo(Member::class); }
}
