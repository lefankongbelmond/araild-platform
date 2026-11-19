<?php

namespace App\Support;

use App\Models\Tenant\MutualSetting;
use Illuminate\Support\Carbon;

/**
 * Règle de SUSPENSION des droits pour cotisations impayées (2026-11-24), réglable par mutuelle.
 *
 * - Retard financier (arriérés, relances, rapports) : toute échéance non réglée dont la date est
 *   passée — inchangé.
 * - Suspension des droits : seulement quand une échéance reste non réglée PLUS de N jours après sa
 *   date (N = délai de grâce, réglage « arrears_grace_days » de la mutuelle). Réglage absent ou 0 =
 *   comportement d'origine (suspension dès le lendemain de l'échéance). Aucune valeur par défaut
 *   n'est inventée.
 */
final class ArrearsPolicy
{
    public const KEY = 'arrears_grace_days';
    public const MAX_DAYS = 180;

    public static function graceDays(): int
    {
        return max(0, min(self::MAX_DAYS, (int) (MutualSetting::int(self::KEY) ?? 0)));
    }

    /** Une échéance non réglée dont la date est STRICTEMENT avant cette date suspend les droits. */
    public static function cutoff(?Carbon $today = null): string
    {
        return ($today ?? now())->copy()->startOfDay()->subDays(self::graceDays())->toDateString();
    }

    /** Filtre une requête sur contribution_schedules : échéances qui suspendent les droits. */
    public static function suspending($query, ?string $cutoff = null)
    {
        return $query->where('status', '!=', 'paid')->whereDate('due_date', '<', $cutoff ?? self::cutoff());
    }

    /**
     * Fin du délai de grâce de l'échéance impayée la plus ancienne encore DANS le délai (null si aucune) :
     * « droits maintenus jusqu'au … » (affichage seulement).
     */
    public static function graceEnd(?string $oldestPastDue): ?string
    {
        $g = self::graceDays();
        if (! $oldestPastDue || $g === 0) {
            return null;
        }
        $end = Carbon::parse($oldestPastDue)->addDays($g);

        return $end->gte(now()->startOfDay()) ? $end->toDateString() : null;
    }
}
