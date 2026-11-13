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
            ['src' => '/brand/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
            ['src' => '/brand/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
            ['src' => '/brand/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
        ];
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
                ['src' => '/brand/appcenter/mutuelle-capture-large.jpg', 'sizes' => '1280x800', 'type' => 'image/jpeg', 'form_factor' => 'wide', 'label' => 'Gestion des adhérents dans MX-CONNECT Mutuelle'],
            ],
            'shortcuts' => [
                ['name' => 'Adhérents', 'url' => '/adherents?source=pwa', 'icons' => [['src' => '/brand/icon-192.png', 'sizes' => '192x192']]],
                ['name' => 'Cotisations', 'url' => '/cotisations?source=pwa', 'icons' => [['src' => '/brand/icon-192.png', 'sizes' => '192x192']]],
            ],
            'related_applications' => [['platform' => 'webapp', 'url' => request()->getSchemeAndHttpHost() . '/app-mutuelle.webmanifest']],
            'prefer_related_applications' => false,
        ];

        return response()->json($manifest, 200, [
            'Content-Type' => 'application/manifest+json; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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

        return redirect('/brand/icon-512.png');
    }
}
