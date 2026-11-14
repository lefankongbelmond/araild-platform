<?php

namespace App\Models\Provider;

use Illuminate\Database\Eloquent\Model;

/** Jeton à usage unique (60 s) pour entrer dans le portail d'une mutuelle depuis l'espace central. */
class ProviderHandoff extends Model
{
    public const UPDATED_AT = null;

    protected $connection = 'central';
    protected $guarded = [];
    protected $casts = ['expires_at' => 'datetime', 'used_at' => 'datetime'];
}
