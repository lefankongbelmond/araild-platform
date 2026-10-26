<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;

/**
 * Résultat chiffré d'un projet. Par défaut « déclaré par l'organisation » ;
 * « vérifié » uniquement après contrôle par l'administration du réseau.
 * Toute modification du chiffre annule la vérification (voir ProjectService).
 */
class ProjectIndicator extends Model
{
    protected $connection = 'central';
    protected $table = 'social_project_indicators';
    protected $guarded = [];
    protected $casts = ['value' => 'float', 'target' => 'float', 'verified_at' => 'datetime'];

    public function project() { return $this->belongsTo(Project::class, 'project_id'); }

    public function isVerified(): bool { return $this->verified_at !== null; }

    /** Nombre au format français, sans décimales inutiles (1 250 ; 12,5). */
    public static function fmt(?float $n): string
    {
        if ($n === null) {
            return '';
        }

        return number_format($n, fmod($n, 1.0) == 0.0 ? 0 : 2, ',', ' ');
    }

    /** Avancement vers la cible (0–100), si une cible est déclarée. */
    public function percent(): ?int
    {
        return $this->target && $this->target > 0 ? (int) min(100, round($this->value / $this->target * 100)) : null;
    }
}
