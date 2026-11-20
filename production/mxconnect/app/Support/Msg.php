<?php

namespace App\Support;

/**
 * Texte traduisible « différé » (multilingue M2b, 2026-11-29) : Msg::of(clé, variables)
 * renvoie [clé, variables] SANS traduire. Sert aux notifications, traduites plus tard dans la langue
 * du destinataire (Social\NotificationService::text). La clé (texte français) est relevée par les
 * outils et le test du catalogue comme un __('…').
 */
final class Msg
{
    public static function of(string $key, array $replace = []): array
    {
        return [$key, $replace];
    }
}
