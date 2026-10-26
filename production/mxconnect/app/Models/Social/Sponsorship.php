<?php

namespace App\Models\Social;

use App\Models\Central\Currency;
use Illuminate\Database\Eloquent\Model;

/**
 * Publication sponsorisée. Aucun paiement n'est encaissé par la plateforme :
 * l'annonceur paie selon les instructions publiées par le réseau, indique la
 * référence du paiement, et l'administration du réseau active la campagne
 * après vérification.
 */
class Sponsorship extends Model
{
    protected $connection = 'central';
    protected $table = 'social_sponsorships';
    protected $guarded = [];
    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date', 'validated_at' => 'datetime'];

    public const STATUSES = [
        'pending_payment' => 'En attente de paiement',
        'active'          => 'Active',
        'paused'          => 'En pause',
        'rejected'        => 'Refusée',
        'completed'       => 'Terminée',
        'cancelled'       => 'Annulée',
    ];

    public const CTA = ['savoir' => 'En savoir plus', 'suivre' => 'Suivre la page', 'contacter' => 'Nous contacter', 'adherer' => 'Adhérer'];

    public function advertiser() { return $this->belongsTo(Advertiser::class, 'advertiser_id'); }
    public function post()       { return $this->belongsTo(Post::class, 'post_id')->withTrashed(); }
    public function currency()   { return $this->belongsTo(Currency::class, 'currency_id'); }

    /** Montant consommé (affichages réalisés × prix pour mille), plafonné au budget. */
    public function spentMinor(): int
    {
        return (int) min($this->budget_minor, intdiv($this->impressions * $this->cpm_minor, 1000));
    }

    public function money(int $minor): string
    {
        return $this->currency ? $this->currency->format($minor) : number_format($minor, 0, ',', ' ');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
