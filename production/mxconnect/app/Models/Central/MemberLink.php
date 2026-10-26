<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;

class MemberLink extends Model
{
    protected $connection = 'central';
    protected $guarded = [];

    public function account() { return $this->belongsTo(PublicAccount::class, 'public_account_id'); }
    public function mutual()  { return $this->belongsTo(Mutual::class, 'mutual_id'); }
}
