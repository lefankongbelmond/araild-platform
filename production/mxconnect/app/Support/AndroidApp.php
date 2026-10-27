<?php

namespace App\Support;

use App\Models\Social\SocialSetting;

/**
 * Réglages de l'application Android (TWA, 2026-11-09) : version minimale acceptée,
 * dernière version, lien de téléchargement, maintenance, empreintes du certificat.
 * Les valeurs saisies par le super-admin priment sur celles du fichier .env.
 */
class AndroidApp
{
    public static function package(): string
    {
        return (string) (SocialSetting::get('android_package') ?: config('mxconnect.android.package'));
    }

    /** Empreintes SHA-256 du certificat (format AA:BB:…), pour /.well-known/assetlinks.json. */
    public static function fingerprints(): array
    {
        $raw = (string) (SocialSetting::get('android_sha256') ?: config('mxconnect.android.sha256'));

        return array_values(array_filter(array_map(fn ($f) => strtoupper(trim($f)), preg_split('/[\s,;]+/', $raw)),
            fn ($f) => (bool) preg_match('/^([0-9A-F]{2}:){31}[0-9A-F]{2}$/', $f)));
    }

    public static function minVersion(): int
    {
        return (int) SocialSetting::get('android_min_version', 1);
    }

    public static function latestVersion(): int
    {
        return max(self::minVersion(), (int) SocialSetting::get('android_latest_version', 1));
    }

    public static function storeUrl(): ?string
    {
        $u = SocialSetting::get('android_store_url') ?: config('mxconnect.android.store_url');

        return $u ?: null;
    }

    public static function maintenance(): ?string
    {
        return SocialSetting::get('android_maintenance') === '1'
            ? (SocialSetting::get('android_maintenance_message') ?: 'MX-CONNECT est en maintenance. Réessayez dans quelques minutes.')
            : null;
    }
}
