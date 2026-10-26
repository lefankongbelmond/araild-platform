<?php

namespace App\Models\Social;

use App\Models\Central\Mutual;
use Illuminate\Database\Eloquent\Model;

class ContactRequest extends Model
{
    protected $connection = 'central';
    protected $table = 'social_contact_requests';
    protected $guarded = [];

    public const SUBJECTS = [
        'information' => 'Demande d’information',
        'adhesion'    => 'Intérêt pour une adhésion',
        'partenariat' => 'Partenariat',
        'autre'       => 'Autre',
    ];

    public function mutual() { return $this->belongsTo(Mutual::class, 'mutual_id'); }
}
