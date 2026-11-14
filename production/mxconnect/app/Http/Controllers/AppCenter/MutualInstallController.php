<?php

namespace App\Http\Controllers\AppCenter;

use App\Http\Controllers\Controller;
use App\Support\AppCenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * MX-CONNECT MUTUELLE installable (2026-11-13), servi sur le SOUS-DOMAINE de chaque mutuelle :
 * une application web n'est installable que depuis son adresse, et la session du personnel
 * est propre à ce sous-domaine. Chaque mutuelle a donc sa propre application (nom, icône,
 * démarrage sur sa page de connexion). Aucune donnée de gestion n'est mise en cache sur
 * l'appareil (voir /sw-mutuelle.js).
 */
class MutualInstallController extends Controller
{
    public function show(Request $request)
    {
        $app = AppCenter::app('mutuelle');
        abort_unless($app && $app->enabled, 404);
        $url = $request->getSchemeAndHttpHost() . '/installer?appareil=android';
        try {
            $qr = (string) \F9WebLtd\QrCode\Facades\QrCode::size(180)->margin(1)->generate($url);
        } catch (\Throwable) {
            $qr = null;
        }

        return view('appcenter.install', [
            'app' => $app,
            'mutualName' => (string) tenant('name'),
            'qr' => $qr,
            'target' => $request->query('appareil') === 'android' ? 'android' : 'pc',
            'apk' => AppCenterController::apkInfo($app),
        ]);
    }

    /** Manifeste propre à la mutuelle : nom, couleur, démarrage sur l'espace de la mutuelle. */
    public function manifest()
    {
        $app = AppCenter::app('mutuelle');
        abort_unless($app && $app->enabled, 404);
        $name = trim((string) tenant('name')) ?: 'Mutuelle';
        $short = mb_strlen($name) <= 15 ? $name : Str::ucfirst((string) tenant('slug'));
        $icons = [
            ['src' => '/installer/fichiers/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
            ['src' => '/installer/fichiers/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
            ['src' => '/installer/fichiers/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
        ];
        $central = rtrim((string) config('app.url'), '/');
        if ($app->icon_path) {
            array_unshift($icons, ['src' => '/installer/icone.png?v=' . optional($app->updated_at)->timestamp, 'sizes' => '512x512', 'type' => 'image/png']);
        }

        $manifest = [
            'name' => $name . ' · MX-CONNECT Mutuelle',
            'short_name' => $short,
            'description' => $app->description,
            'lang' => 'fr',
            'id' => '/?app=mx-mutuelle',
            'start_url' => '/?source=pwa',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'any',
            'background_color' => '#F4F7FB',
            'theme_color' => '#0B2559',
            'categories' => ['business', 'finance', 'health'],
            'icons' => $icons,
            'screenshots' => [
                ['src' => $central . '/brand/appcenter/mutuelle-capture-large.jpg', 'sizes' => '1280x800', 'type' => 'image/jpeg', 'form_factor' => 'wide', 'label' => 'Gestion des adhérents dans MX-CONNECT Mutuelle'],
            ],
            'shortcuts' => [
                ['name' => 'Adhérents', 'url' => '/adherents?source=pwa', 'icons' => [['src' => '/installer/fichiers/icon-192.png', 'sizes' => '192x192']]],
                ['name' => 'Cotisations', 'url' => '/cotisations?source=pwa', 'icons' => [['src' => '/installer/fichiers/icon-192.png', 'sizes' => '192x192']]],
            ],
            'related_applications' => [['platform' => 'webapp', 'url' => request()->getSchemeAndHttpHost() . '/app-mutuelle.webmanifest']],
            'prefer_related_applications' => false,
        ];

        return response()->json($manifest, 200, [
            'Content-Type' => 'application/manifest+json; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Fichiers servis PAR L'APPLICATION sur le sous-domaine (2026-11-14) : en production, les
     * fichiers statiques ne sont servis que par le domaine principal ; or le script, les icônes du
     * manifeste et le service worker doivent venir de la MÊME adresse que la mutuelle.
     */
    private const FILES = [
        'mx-appcenter.js' => ['js/mx-appcenter.js', 'text/javascript; charset=utf-8'],
        'icon-192.png' => ['brand/icon-192.png', 'image/png'],
        'icon-512.png' => ['brand/icon-512.png', 'image/png'],
        'icon-maskable-512.png' => ['brand/icon-maskable-512.png', 'image/png'],
        'apple-touch-icon.png' => ['brand/apple-touch-icon.png', 'image/png'],
        'mx-mark-128.webp' => ['brand/mx-mark-128.webp', 'image/webp'],
        // Lecteur de QR du portail prestataire (2026-11-15) : en production, le sous-domaine ne sert aucun /js.
        'mx-scan.js' => ['js/mx-scan.js', 'text/javascript; charset=utf-8'],
        'jsqr-1.4.0.min.js' => ['js/vendor/jsqr-1.4.0.min.js', 'text/javascript; charset=utf-8'],
    ];

    public function file(string $name)
    {
        abort_unless(isset(self::FILES[$name]), 404);
        [$rel, $type] = self::FILES[$name];
        $path = public_path($rel);
        abort_unless(is_file($path), 404);

        return response()->file($path, ['Content-Type' => $type, 'Cache-Control' => 'public, max-age=86400', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** Service worker de l'application de la mutuelle (portée « / » du sous-domaine). */
    public function worker()
    {
        $path = public_path('sw-mutuelle.js');
        abort_unless(is_file($path), 404);

        return response(file_get_contents($path), 200, [
            'Content-Type' => 'text/javascript; charset=utf-8',
            'Service-Worker-Allowed' => '/',
            'Cache-Control' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Page « Pas de connexion » mise en cache par le service worker. */
    public function offline()
    {
        $path = public_path('hors-ligne-mutuelle.html');
        abort_unless(is_file($path), 404);

        return response(file_get_contents($path), 200, ['Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'no-cache']);
    }

    public function event(Request $request)
    {
        $d = $request->validate([
            'event' => ['required', 'in:' . implode(',', AppCenter::EVENTS)],
            'os' => ['nullable', 'string', 'max:12'],
            'browser' => ['nullable', 'string', 'max:12'],
            'device' => ['nullable', 'regex:/^[a-f0-9]{32}$/'],
        ]);
        AppCenter::record('mutuelle', $d['event'], (string) ($d['os'] ?? ''), (string) ($d['browser'] ?? ''), $d['device'] ?? null, (string) tenant('id'));

        return response()->noContent();
    }

    public function icon()
    {
        $app = AppCenter::app('mutuelle');
        if ($app && $app->icon_path && Storage::disk('local')->exists($app->icon_path)) {
            return response(Storage::disk('local')->get($app->icon_path), 200, [
                'Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=3600', 'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return $this->file('icon-512.png');
    }
}
