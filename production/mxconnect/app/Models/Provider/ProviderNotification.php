<?php

namespace App\Models\Provider;

use Illuminate\Database\Eloquent\Model;

/** Avis d'une mutuelle à un établissement de soins (phase 6, 2026-11-25). Aucune donnée médicale. */
class ProviderNotification extends Model
{
    protected $connection = 'central';
    protected $guarded = [];
    public const UPDATED_AT = null;
    protected $casts = ['created_at' => 'datetime'];
}
