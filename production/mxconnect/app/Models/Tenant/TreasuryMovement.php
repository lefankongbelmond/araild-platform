<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class TreasuryMovement extends Model
{
    protected $guarded = [];
    protected $casts = ['moved_on' => 'date', 'amount_minor' => 'integer'];
}
