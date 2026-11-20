<?php

namespace App\Support;

use App\Models\Tenant\MutualSetting;

/**
 * Identité de la mutuelle telle qu'elle l'affiche elle-même (fiche « Identité et langue », M3a,
 * 2026-11-30). Coordonnées enregistrées dans la base de la mutuelle (mutual_settings) ; la langue
 * par défaut reste la colonne centrale mutuals.locale_id (celle que lit SetTenantLocale).
 * À appeler dans le contexte de la mutuelle. Rien n'est inventé : une coordonnée absente
 * n'est pas affichée.
 */
final class MutualIdentity
{
    public const KEYS = ['phone' => 'identity_phone', 'email' => 'identity_email', 'address' => 'identity_address'];

    /** Coordonnées renseignées : ['phone' => …, 'email' => …, 'address' => …] (clés absentes si vides). */
    public static function contacts(): array
    {
        $out = [];
        try {
            foreach (self::KEYS as $k => $key) {
                $v = MutualSetting::get($key);
                if ($v !== null && trim($v) !== '') {
                    $out[$k] = trim($v);
                }
            }
        } catch (\Throwable) {
            return [];   // base de la mutuelle indisponible : rien plutôt qu'une erreur sur un reçu
        }

        return $out;
    }

    /** Une ligne lisible pour l'en-tête d'un document imprimé (adresse · téléphone · e-mail). */
    public static function line(): string
    {
        $c = self::contacts();

        return implode(' · ', array_filter([$c['address'] ?? null, $c['phone'] ?? null, $c['email'] ?? null]));
    }

    /** Code de la langue par défaut de la mutuelle courante (null si non définie ou inactive). */
    public static function locale(): ?string
    {
        $code = \App\Http\Middleware\SetLocale::localeCode((int) (tenant('locale_id') ?? 0));

        return $code && Locales::isActive($code) ? $code : null;
    }
}
