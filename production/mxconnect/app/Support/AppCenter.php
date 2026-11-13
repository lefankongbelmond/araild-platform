<?php

namespace App\Support;

use App\Models\AppCenter\AppCenterApp;
use App\Models\AppCenter\AppCenterInstall;
use App\Models\AppCenter\AppCenterStat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * App Center (2026-11-13) : les deux applications installables de l'écosystème et leurs
 * statistiques ANONYMES.
 *  - « mutuelle » : l'espace de gestion d'une mutuelle, installable depuis SON sous-domaine
 *    (une application par mutuelle : une application web ne couvre qu'une seule adresse) ;
 *  - « social » : le réseau social, installable depuis le domaine principal.
 * Statistiques : compteurs journaliers par application / événement / système / navigateur /
 * version, et une ligne par installation confirmée (empreinte d'un identifiant aléatoire tiré
 * par l'appareil). Aucun compte, aucune adresse IP, aucune donnée de santé.
 * Un clic sur « Installer » n'est jamais compté comme une installation : seule une installation
 * signalée par le navigateur (appinstalled) ou une ouverture en mode application la confirme.
 */
class AppCenter
{
    public const KEYS = ['mutuelle', 'social'];
    public const EVENTS = ['click_pc', 'click_android', 'prompt', 'accepted', 'dismissed', 'installed', 'launched', 'guide', 'apk_download', 'open', 'qr'];
    public const OS = ['windows', 'macos', 'linux', 'android', 'ios', 'chromeos', 'other'];
    public const BROWSERS = ['chrome', 'edge', 'firefox', 'safari', 'samsung', 'opera', 'other'];
    public const ACTIVE_DAYS = 30;

    public const LABELS = [
        'click_pc' => 'Clics « Installer sur PC »', 'click_android' => 'Clics « Installer sur Android »',
        'prompt' => 'Fenêtres d’installation ouvertes', 'accepted' => 'Installations acceptées dans la fenêtre',
        'dismissed' => 'Fenêtres refusées', 'installed' => 'Installations confirmées par le navigateur',
        'launched' => 'Ouvertures en mode application', 'guide' => 'Guides consultés',
        'apk_download' => 'Téléchargements de l’APK', 'open' => 'Clics « Ouvrir »', 'qr' => 'QR code affiché (PC → Android)',
    ];

    public static function ready(): bool
    {
        try {
            return Schema::connection('central')->hasTable('app_center_apps');
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array<string, AppCenterApp> */
    public static function apps(): array
    {
        if (! self::ready()) {
            return [];
        }
        $apps = Cache::remember('appcenter:apps', 60, fn () => AppCenterApp::query()->get()->keyBy('key')->all());
        if (count($apps) < count(self::KEYS)) {
            self::seed();
            Cache::forget('appcenter:apps');
            $apps = AppCenterApp::query()->get()->keyBy('key')->all();
        }

        // Version « automatique » : celle de la livraison (fichier VERSION, ex. 2026.11.13) et sa date.
        [$version, $date] = self::platformVersion();
        foreach ($apps as $app) {
            if ($app->version_auto && $version) {
                $app->version = $version;
                $app->released_on = $date;
            }
        }

        return $apps;
    }

    /** @return array{0: ?string, 1: ?string} version de la plateforme et date déduite (AAAA.MM.JJ). */
    public static function platformVersion(): array
    {
        $v = trim((string) @file_get_contents(base_path('VERSION')));
        $date = preg_match('/^(\d{4})\.(\d{2})\.(\d{2})/', $v, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? "{$m[1]}-{$m[2]}-{$m[3]}" : null;

        return [$v ?: null, $date];
    }

    public static function app(string $key): ?AppCenterApp
    {
        return self::apps()[$key] ?? null;
    }

    public static function enabled(string $key): bool
    {
        return (bool) self::app($key)?->enabled;
    }

    public static function forget(): void
    {
        Cache::forget('appcenter:apps');
    }

    /** Textes de départ (modifiables dans l'espace super-admin). N'écrase jamais une application existante. */
    public static function seed(): void
    {
        $version = trim((string) @file_get_contents(base_path('VERSION'))) ?: '1.0.0';
        $defaults = [
            'mutuelle' => [
                'name' => 'MX-CONNECT MUTUELLE',
                'tagline' => 'La gestion numérique des mutuelles de santé',
                'description' => 'Une solution numérique intégrée pour moderniser la gestion des mutuelles de santé, des adhérents, des cotisations, des prestations et du suivi administratif et financier.',
                'features' => ['Gestion des mutuelles', 'Gestion des adhérents et ayants droit', 'Gestion des cotisations',
                    'Gestion des prestations et remboursements', 'Gestion des formations sanitaires partenaires',
                    'Suivi des droits des bénéficiaires', 'Gestion administrative et financière',
                    'Rapports et tableaux de bord', 'Gestion des utilisateurs et des rôles'],
            ],
            'social' => [
                'name' => 'MX-CONNECT SOCIAL',
                'tagline' => 'Le réseau social de l’écosystème MX-CONNECT',
                'description' => 'Le réseau social de l’écosystème MX-CONNECT. Une plateforme pour connecter les citoyens, les membres des mutuelles, les organisations communautaires et les acteurs du développement.',
                'features' => ['Création de profils utilisateurs', 'Publications et actualités', 'Photos et vidéos',
                    'Commentaires et réactions', 'Abonnements et connexions', 'Messagerie privée',
                    'Groupes et communautés', 'Notifications', 'Pages des organisations',
                    'Événements et activités communautaires'],
            ],
        ];
        foreach ($defaults as $key => $d) {
            AppCenterApp::query()->firstOrCreate(['key' => $key], $d + ['enabled' => true, 'version' => $version, 'released_on' => now()->toDateString()]);
        }
    }

    // ---------------------------------------------------------------- statistiques

    /** Détection côté serveur (téléchargement direct de l'APK, sans JavaScript). */
    public static function parseUa(?string $ua): array
    {
        $ua = (string) $ua;
        $os = match (true) {
            (bool) preg_match('/Android/i', $ua) => 'android',
            (bool) preg_match('/iPhone|iPad|iPod/', $ua) => 'ios',
            str_contains($ua, 'Windows') => 'windows',
            str_contains($ua, 'CrOS') => 'chromeos',
            (bool) preg_match('/Macintosh|Mac OS X/', $ua) => 'macos',
            str_contains($ua, 'Linux') => 'linux',
            default => 'other',
        };
        $browser = match (true) {
            (bool) preg_match('#Edg(e|A|iOS)?/#', $ua) => 'edge',
            str_contains($ua, 'SamsungBrowser') => 'samsung',
            (bool) preg_match('#OPR/|Opera#', $ua) => 'opera',
            (bool) preg_match('/Firefox|FxiOS/', $ua) => 'firefox',
            (bool) preg_match('#CriOS|Chrome/#', $ua) => 'chrome',
            str_contains($ua, 'Safari') => 'safari',
            default => 'other',
        };

        return [$os, $browser];
    }

    public static function os(?string $v): string
    {
        return in_array($v, self::OS, true) ? $v : 'other';
    }

    public static function browser(?string $v): string
    {
        return in_array($v, self::BROWSERS, true) ? $v : 'other';
    }

    /**
     * Enregistre un événement. Pour « installed » et « launched », une ligne d'installation est
     * créée ou mise à jour à partir de l'empreinte de l'identifiant aléatoire de l'appareil.
     */
    public static function record(string $app, string $event, string $os, string $browser, ?string $deviceId = null, ?string $mutualId = null): void
    {
        if (! in_array($app, self::KEYS, true) || ! in_array($event, self::EVENTS, true) || ! self::ready()) {
            return;
        }
        $os = self::os($os);
        $browser = self::browser($browser);
        $version = (string) (self::app($app)?->version ?? '');

        AppCenterStat::query()->upsert(
            [['day' => now()->toDateString(), 'app' => $app, 'event' => $event, 'os' => $os, 'browser' => $browser, 'version' => $version, 'count' => 1]],
            ['day', 'app', 'event', 'os', 'browser', 'version'],
            ['count' => DB::raw('count + 1')]
        );

        if (in_array($event, ['installed', 'launched'], true) && $deviceId && preg_match('/^[a-f0-9]{32}$/', $deviceId)) {
            $hash = hash('sha256', config('app.key') . '|' . $app . '|' . $deviceId);
            $install = AppCenterInstall::query()->firstOrNew(['app' => $app, 'device_hash' => $hash]);
            if (! $install->exists) {
                $install->fill([
                    'mutual_id' => $mutualId, 'os' => $os, 'browser' => $browser,
                    'form' => in_array($os, ['android', 'ios'], true) ? 'mobile' : 'pc',
                    'version' => $version, 'source' => $event, 'installed_at' => now(),
                ]);
            }
            if ($event === 'launched') {
                $install->last_launch_at = now();
            }
            $install->save();
        }
    }

    /** Synthèse pour l'espace super-admin, sur une période (jours). */
    public static function summary(int $days = 30): array
    {
        if (! self::ready()) {
            return [];
        }
        $from = now()->subDays($days - 1)->toDateString();
        $out = [];
        foreach (self::KEYS as $key) {
            $events = AppCenterStat::query()->where('app', $key)->where('day', '>=', $from)
                ->groupBy('event')->selectRaw('event, sum(count) as n')->pluck('n', 'event')->map(fn ($n) => (int) $n)->all();
            $installs = AppCenterInstall::query()->where('app', $key);
            $out[$key] = [
                'events' => $events,
                'installs_total' => (clone $installs)->count(),
                'installs_period' => (clone $installs)->where('installed_at', '>=', $from)->count(),
                'installs_pc' => (clone $installs)->where('form', 'pc')->count(),
                'installs_mobile' => (clone $installs)->where('form', 'mobile')->count(),
                'installs_android' => (clone $installs)->where('os', 'android')->count(),
                'active' => (clone $installs)->where('last_launch_at', '>=', now()->subDays(self::ACTIVE_DAYS))->count(),
                'by_os' => (clone $installs)->groupBy('os')->selectRaw('os, count(*) as n')->orderByDesc('n')->pluck('n', 'os')->all(),
                'by_browser' => (clone $installs)->groupBy('browser')->selectRaw('browser, count(*) as n')->orderByDesc('n')->pluck('n', 'browser')->all(),
                'by_version' => (clone $installs)->groupBy('version')->selectRaw('version, count(*) as n')->orderByDesc('n')->pluck('n', 'version')->all(),
                'by_mutual' => $key === 'mutuelle'
                    ? (clone $installs)->whereNotNull('mutual_id')->groupBy('mutual_id')->selectRaw('mutual_id, count(*) as n')->orderByDesc('n')->pluck('n', 'mutual_id')->all()
                    : [],
            ];
        }

        return $out;
    }
}
