<?php

namespace App\Models\Social;

use App\Models\Central\Mutual;
use Illuminate\Database\Eloquent\Model;

/** Abonnement d'un compte à une mutuelle (≠ adhésion comme bénéficiaire). */
class Follow extends Model
{
    protected $connection = 'central';
    protected $table = 'social_follows';
    protected $guarded = [];
    protected $casts = ['priority' => 'boolean'];

    public function mutual() { return $this->belongsTo(Mutual::class, 'mutual_id'); }
}
