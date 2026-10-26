<?php

namespace App\Models\Social;

use App\Models\Central\Mutual;
use Illuminate\Database\Eloquent\Model;

/** Vitrine publique d'une mutuelle (base centrale). */
class MutualProfile extends Model
{
    protected $connection = 'central';
    protected $primaryKey = 'mutual_id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = ['services' => 'array', 'is_demo' => 'boolean'];

    public const CATEGORIES = [
        'mutuelle'      => 'Mutuelle de santé',
        'osc'           => 'Organisation de la société civile',
        'etablissement' => 'Établissement de santé',
        'partenaire'    => 'Partenaire technique et financier',
        'organisation'  => 'Organisation communautaire',
    ];

    /**
     * Filtre d'annuaire par type de structure. « organisations » regroupe tout
     * ce qui n'est pas une mutuelle ; une mutuelle sans fiche est une mutuelle.
     */
    public static function applyCategory($query, ?string $category)
    {
        if ($category === 'organisations') {
            return $query->whereHas('profile', fn ($p) => $p->where('category', '!=', 'mutuelle'));
        }
        if ($category === 'mutuelle') {
            return $query->where(fn ($w) => $w->whereDoesntHave('profile')
                ->orWhereHas('profile', fn ($p) => $p->where('category', 'mutuelle')->orWhereNull('category')));
        }
        if ($category && isset(self::CATEGORIES[$category])) {
            return $query->whereHas('profile', fn ($p) => $p->where('category', $category));
        }

        return $query;
    }

    public function mutual() { return $this->belongsTo(Mutual::class, 'mutual_id'); }
    public function owner()  { return $this->belongsTo(\App\Models\Central\PublicAccount::class, 'owner_account_id'); }

    /** Page créée par un membre du réseau (sans espace de gestion ni base de données). */
    public function isPage(): bool { return (bool) $this->owner_account_id; }

    public function logoUrl(): ?string  { return \App\Services\Social\MediaService::url($this->logo_path); }
    public function coverUrl(): ?string { return \App\Services\Social\MediaService::url($this->cover_path); }
}
