<?php

namespace App\Models\Social;

use App\Models\Central\PublicAccount;
use Illuminate\Database\Eloquent\Model;

/** Retour d'un testeur de l'application bêta (2026-11-09). */
class BetaFeedback extends Model
{
    protected $connection = 'central';
    protected $table = 'beta_feedback';
    protected $guarded = [];

    public const CATEGORIES = ['bug' => 'Un problème', 'idea' => 'Une idée', 'question' => 'Une question', 'other' => 'Autre'];
    public const STATUSES = ['new' => 'Nouveau', 'read' => 'Lu', 'done' => 'Traité'];

    public function account() { return $this->belongsTo(PublicAccount::class, 'public_account_id'); }
}
