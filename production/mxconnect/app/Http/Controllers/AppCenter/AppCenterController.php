<?php

namespace App\Http\Controllers\AppCenter;

use App\Http\Controllers\Controller;
use App\Models\Central\Domain;
use App\Models\Central\Mutual;
use App\Support\AppCenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * App Center public (domaine principal, 2026-11-13) : présentation et installation des
 * applications MX-CONNECT MUTUELLE et MX-CONNECT SOCIAL.
 *  - Social : installation directe (la page porte le manifeste du réseau social) ;
 *  - Mutuelle : choix de la mutuelle, puis page /installer de SON sous-domaine (une
 *    application web ne peut être installée que depuis son adresse) ;
 *  - APK : servi seulement s'il a été déposé ET vérifié (signature officielle).
 */
class AppCenterController extends Controller
{
    public function index(Request $request)
    {
        $apps = AppCenter::apps();
        abort_if(! $apps, 503, 'App Center indisponible : mise à jour en cours.');

        $mutuals = Mutual::query()->operational()->where('status', '!=', 'suspended')
            ->whereIn('id', Domain::query()->select('mutual_id'))->orderBy('name')->get(['id', 'name', 'slug']);

        $qr = [];
        foreach (['social' => route('appcenter.index', ['installer' => 'social']) . '#social', 'mutuelle' => route('appcenter.index', ['installer' => 'mutuelle']) . '#mutuelle'] as $k => $url) {
            try {
                $qr[$k] = (string) \F9WebLtd\QrCode\Facades\QrCode::size(180)->margin(1)->generate($url);
            } catch (\Throwable) {
                $qr[$k] = null;
            }
        }

        return view('appcenter.index', [
            'apps' => $apps,
            'mutuals' => $mutuals,
            'qr' => $qr,
            'apk' => collect($apps)->map(fn ($a) => $this->apkInfo($a))->all(),
            'suggest' => in_array($request->query('installer'), AppCenter::KEYS, true) ? $request->query('installer') : null,
        ]);
    }

    /** Personnel d'une mutuelle : page d'installation de SA mutuelle. */
    public function mutual(Request $request)
    {
        abort_unless(AppCenter::enabled('mutuelle'), 404);
        $data = $request->validate([
            'mutuelle' => ['required', 'string', 'max:120'],
            'appareil' => ['nullable', 'in:pc,android'],
        ], ['mutuelle.required' => 'Indiquez le nom ou l’identifiant de votre mutuelle.']);

        $q = Str::lower(trim($data['mutuelle']));
        $q = explode('.', (string) preg_replace('#^https?://#', '', $q))[0];   // « mutueltest.m.araild.com » → « mutueltest »
        $mutual = Mutual::query()->operational()->where('status', '!=', 'suspended')
            ->where(fn ($w) => $w->where('slug', $q)->orWhereRaw('lower(name) = ?', [Str::lower(trim($data['mutuelle']))]))->first();
        $domain = $mutual ? Domain::where('mutual_id', $mutual->id)->value('domain') : null;
        if (! $domain) {
            return redirect()->to(route('appcenter.index') . '#mutuelle')->withInput()
                ->withErrors(['mutuelle' => 'Aucune mutuelle trouvée. Saisissez le nom exact ou l’identifiant communiqué par votre mutuelle.']);
        }
        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';
        $port = parse_url((string) config('app.url'), PHP_URL_PORT);

        return redirect()->away("{$scheme}://{$domain}" . ($port ? ":{$port}" : '') . '/installer?appareil=' . ($data['appareil'] ?? 'pc'));
    }

    /** Événement anonyme (clic, fenêtre d'installation, installation confirmée, ouverture…). */
    public function event(Request $request)
    {
        $d = $request->validate([
            'app' => ['required', 'in:' . implode(',', AppCenter::KEYS)],
            'event' => ['required', 'in:' . implode(',', AppCenter::EVENTS)],
            'os' => ['nullable', 'string', 'max:12'],
            'browser' => ['nullable', 'string', 'max:12'],
            'device' => ['nullable', 'regex:/^[a-f0-9]{32}$/'],
        ]);
        AppCenter::record($d['app'], $d['event'], (string) ($d['os'] ?? ''), (string) ($d['browser'] ?? ''), $d['device'] ?? null);

        return response()->noContent();
    }

    /** APK officiel : seulement s'il a été vérifié au dépôt. */
    public function apk(Request $request, string $key)
    {
        $app = AppCenter::app($key);
        abort_unless($app && $app->enabled && $app->hasApk() && Storage::disk('local')->exists($app->apk_path), 404);
        [$os, $browser] = AppCenter::parseUa($request->userAgent());
        AppCenter::record($key, 'apk_download', $os, $browser);

        return Storage::disk('local')->download($app->apk_path, 'mx-connect-' . $key . '-' . Str::slug((string) $app->apk_version) . '.apk', [
            'Content-Type' => 'application/vnd.android.package-archive',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store',
        ]);
    }

    /** Icône déposée dans l'espace super-admin (sinon icône par défaut). */
    public function icon(string $key)
    {
        $app = AppCenter::app($key);
        if ($app && $app->icon_path && Storage::disk('local')->exists($app->icon_path)) {
            return response(Storage::disk('local')->get($app->icon_path), 200, [
                'Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=3600', 'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return redirect('/brand/icon-512.png');
    }

    public static function apkInfo($app): ?array
    {
        if (! $app || ! $app->hasApk()) {
            return null;
        }

        return [
            'url' => route('appcenter.apk', $app->key),
            'version' => $app->apk_version,
            'size' => number_format(($app->apk_size ?? 0) / 1048576, 1, ',', ' ') . ' Mo',
            'signer' => substr((string) $app->apk_signer, 0, 23) . '…',
            'date' => optional($app->apk_uploaded_at)->format('d/m/Y'),
        ];
    }
}
