<?php

namespace App\Support;

use App\Models\Provider\ProviderMembership;
use App\Models\Provider\ProviderNotification;
use Illuminate\Support\Facades\Schema;

/**
 * Avis aux établissements de soins (Portail prestataire V2, phase 6 ; 2026-11-25).
 *
 * Émis depuis la MUTUELLE (contexte tenant) quand elle décide : prise en charge validée ou rejetée,
 * facture payée ou rejetée, accord préalable accordé ou refusé, demande d'affiliation acceptée ou
 * refusée. Écrits dans la base CENTRALE, lus dans l'espace prestataire, filtrés par le droit
 * d'établissement de chaque personne (un médecin ne voit pas les paiements, par exemple).
 * JAMAIS de donnée médicale (ni acte, ni diagnostic) : numéro, code adhérent, date, montant, motif.
 * Un échec d'écriture n'empêche jamais la décision de la mutuelle.
 */
final class ProviderNotifier
{
    public static function notify(int $careProviderId, string $kind, string $title, ?string $body, ?string $target, string $permission): void
    {
        if ($careProviderId <= 0) {
            return;
        }
        try {
            if (! Schema::connection('central')->hasTable('provider_notifications')) {
                return;
            }
            $mutual = function_exists('tenant') && tenant() ? tenant() : null;
            ProviderNotification::create([
                'care_provider_id' => $careProviderId, 'mutual_id' => $mutual ? (string) $mutual->getTenantKey() : null,
                'kind' => $kind, 'title' => mb_substr(($mutual ? $mutual->name . ' · ' : '') . $title, 0, 160),
                'body' => $body, 'target' => $target, 'permission' => $permission,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Avis visibles par cette personne dans cet établissement (selon ses droits). */
    public static function visibleFor(ProviderMembership $m)
    {
        $perms = array_values(array_filter(ProviderAccess::all(), fn ($p) => $m->can($p)));

        return ProviderNotification::where('care_provider_id', $m->care_provider_id)->whereIn('permission', $perms ?: ['-']);
    }

    public static function unread(ProviderMembership $m): int
    {
        try {
            return Schema::connection('central')->hasTable('provider_notifications')
                ? self::visibleFor($m)->where('id', '>', (int) ($m->notifications_seen_id ?? 0))->count() : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    /** Montant lisible dans la devise de la mutuelle en cours. */
    public static function money(int $minor): string
    {
        $cur = \App\Models\Central\Currency::on('central')->find(tenant('currency_id'));

        return $cur ? $cur->format($minor) : number_format($minor, 0, ',', ' ');
    }
}
