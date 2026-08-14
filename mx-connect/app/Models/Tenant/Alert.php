<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    protected $guarded = [];
    protected $casts = ['details' => 'array'];

    public function scopeOpen($q)  { return $q->where('status', 'open'); }
    public function scopeFor($q, string $type, int $id) { return $q->where('object_type', $type)->where('object_id', $id); }
}
