<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class PrescribedMedicine extends Model
{
    protected $guarded = [];
    protected $casts = ['quantity' => 'integer', 'amount_minor' => 'integer'];

    public function prestation() { return $this->belongsTo(Prestation::class); }
    public function medicine()   { return $this->belongsTo(Medicine::class); }
}
