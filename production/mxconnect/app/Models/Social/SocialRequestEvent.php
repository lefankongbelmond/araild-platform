<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;

/** Historique d'une demande (qui a changé quel statut, quand). */
class SocialRequestEvent extends Model
{
    protected $connection = 'central';
    protected $table = 'social_request_events';
    protected $guarded = [];
    public const UPDATED_AT = null;
    protected $casts = ['created_at' => 'datetime'];
}
