<?php

namespace App\Support;

use App\Models\Social\SocialSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Protection contre la surcharge du réseau social.
 *
 * Mode allégé : activé à la main par l'administration du réseau, ou
 * automatiquement pendant 10 minutes quand une part importante des pages
 * répond lentement. Il réduit le travail du serveur sans rien casser :
 *  - moins de publications par page (chargement continu conservé) ;
 *  - pas d'aperçu de lien (pas d'appel vers d'autres sites) ;
 *  - envoi de nouvelles vidéos suspendu (lecture conservée) ;
 *  - pas de lecture automatique des vidéos ;
 *  - pages publiques mises en cache 30 s pour les visiteurs non connectés.
 */
class SocialLoad
{
    public const SLOW_MS = 2500;

    public static function light(): bool
    {
        try {
            return SocialSetting::get('light_mode') === '1' || (bool) Cache::get('social:light_auto');
        } catch (\Throwable) {
            return false;
        }
    }

    public static function autoUntil(): ?int
    {
        return Cache::get('social:light_auto');
    }

    /** Enregistre la durée d'une page ; bascule en mode allégé si trop de pages sont lentes. */
    public static function record(float $ms): void
    {
        if (SocialSetting::get('light_auto', '1') !== '1') {
            return;   // bascule automatique désactivée par l'administration
        }
        $bucket = 'social:load:' . intdiv(time(), 60);
        $stats = Cache::get($bucket, ['n' => 0, 'slow' => 0]);
        $stats['n']++;
        if ($ms >= self::SLOW_MS) {
            $stats['slow']++;
        }
        Cache::put($bucket, $stats, 180);
        if ($stats['n'] >= 20 && $stats['slow'] / $stats['n'] >= 0.4 && ! Cache::has('social:light_auto')) {
            Cache::put('social:light_auto', time() + 600, 600);
            \Illuminate\Support\Facades\Log::warning('Réseau social : mode allégé activé automatiquement (pages lentes).', $stats);
        }
    }
}
