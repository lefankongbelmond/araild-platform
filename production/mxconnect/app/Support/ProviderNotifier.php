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
    /**
     * Titre et texte : une chaîne simple, un Msg::of(clé française, valeurs), ou une liste de Msg
     * (morceaux joints par « · »). Les valeurs peuvent elles-mêmes être un Msg ou ['__' => libellé].
     * La forme traduisible est gardée (colonne i18n) pour l'affichage dans la langue de chaque personne ;
     * title/body reçoivent le texte français (multilingue M4, 2026-12-03).
     */
    public static function notify(int $careProviderId, string $kind, string|array $title, string|array|null $body, ?string $target, string $permission): void
    {
        if ($careProviderId <= 0) {
            return;
        }
        try {
            if (! Schema::connection('central')->hasTable('provider_notifications')) {
                return;
            }
            $mutual = function_exists('tenant') && tenant() ? tenant() : null;
            $name = $mutual ? (string) $mutual->name : null;
            $row = [
                'care_provider_id' => $careProviderId, 'mutual_id' => $mutual ? (string) $mutual->getTenantKey() : null,
                'kind' => $kind, 'title' => mb_substr(($name ? $name . ' · ' : '') . self::render($title, 'fr'), 0, 160),
                'body' => $body === null ? null : self::render($body, 'fr'), 'target' => $target, 'permission' => $permission,
            ];
            if ((is_array($title) || is_array($body)) && Schema::connection('central')->hasColumn('provider_notifications', 'i18n')) {
                $row['i18n'] = ['t' => $title, 'b' => $body, 'm' => $name];   // cast « array » du modèle
            }
            ProviderNotification::create($row);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Rend un texte d'avis (chaîne, Msg ou liste de Msg) dans la langue donnée (par défaut : celle affichée). */
    public static function render(string|array|null $text, ?string $locale = null): string
    {
        if ($text === null) {
            return '';
        }
        if (is_string($text)) {
            return $text;
        }
        if (! is_string($text[0] ?? null)) {   // liste de morceaux
            return implode(' · ', array_map(fn ($p) => self::render($p, $locale), array_values($text)));
        }
        $replace = [];
        foreach ($text[1] ?? [] as $k => $v) {
            $replace[$k] = match (true) {
                is_array($v) && isset($v['__']) => __((string) $v['__'], [], $locale),
                is_array($v) => self::render($v, $locale),
                default => (string) $v,
            };
        }

        return __((string) $text[0], $replace, $locale);
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
