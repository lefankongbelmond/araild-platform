<?php

namespace App\Models\Social;

use App\Models\Central\Mutual;
use App\Models\Central\PublicAccount;
use Illuminate\Database\Eloquent\Model;

/** Gestionnaire d'une page : publie au nom de la page (admin = gère aussi les gestionnaires). */
class PageAdmin extends Model
{
    protected $connection = 'central';
    protected $table = 'social_page_admins';
    protected $guarded = [];

    public const ROLES = ['admin' => 'Administrateur', 'editor' => 'Éditeur (publie au nom de la page)'];

    public function account() { return $this->belongsTo(PublicAccount::class, 'public_account_id'); }
    public function mutual()  { return $this->belongsTo(Mutual::class, 'mutual_id'); }
}
