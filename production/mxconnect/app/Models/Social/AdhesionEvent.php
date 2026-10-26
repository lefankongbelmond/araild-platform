<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;

/** Historique d'un dossier d'adhésion (qui a changé quel statut, quand). */
class AdhesionEvent extends Model
{
    protected $connection = 'central';
    protected $guarded = [];
    public const UPDATED_AT = null;
    protected $casts = ['created_at' => 'datetime'];
}
