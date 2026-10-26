<?php

namespace App\Models\Social;

use App\Models\Central\Mutual;
use App\Models\Central\PublicAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Demande d'adhésion envoyée en ligne à une mutuelle. Données personnelles
 * (pas de données médicales) : visibles uniquement par le demandeur et par les
 * rôles habilités de la mutuelle concernée.
 */
class AdhesionRequest extends Model
{
    protected $connection = 'central';
    protected $guarded = [];
    protected $casts = [
        'birth_date' => 'date', 'beneficiaries' => 'array',
        'consent_at' => 'datetime', 'decided_at' => 'datetime',
    ];

    public const STATUSES = [
        'submitted'      => 'Envoyée',
        'in_review'      => 'En cours d’étude',
        'info_requested' => 'Informations demandées',
        'accepted'       => 'Acceptée',
        'rejected'       => 'Refusée',
        'withdrawn'      => 'Retirée',
    ];
    /** Statuts encore « ouverts » (une seule demande ouverte par mutuelle et par compte). */
    public const OPEN = ['submitted', 'in_review', 'info_requested'];

    public const RELATIONS = ['conjoint' => 'Conjoint(e)', 'enfant' => 'Enfant', 'parent' => 'Parent', 'autre' => 'Autre'];
    public const DOC_KINDS = ['piece_identite' => 'Pièce d’identité', 'photo' => 'Photo d’identité', 'justificatif' => 'Justificatif (domicile, emploi…)', 'autre' => 'Autre document'];

    public function mutual()    { return $this->belongsTo(Mutual::class, 'mutual_id'); }
    public function account()   { return $this->belongsTo(PublicAccount::class, 'public_account_id'); }
    public function documents() { return $this->hasMany(AdhesionDocument::class); }
    public function events()    { return $this->hasMany(AdhesionEvent::class)->orderBy('id'); }
    public function conversation() { return $this->hasOne(Conversation::class); }

    public function isOpen(): bool { return in_array($this->status, self::OPEN, true); }
    public function statusLabel(): string { return self::STATUSES[$this->status] ?? $this->status; }
    public function getNameAttribute(): string { return trim($this->first_name . ' ' . $this->last_name); }

    public static function newReference(): string
    {
        do {
            $ref = 'ADH-' . now()->format('Y') . '-' . strtoupper(Str::random(6));
        } while (static::where('reference', $ref)->exists());

        return $ref;
    }

    public function getRouteKeyName(): string { return 'reference'; }
}
