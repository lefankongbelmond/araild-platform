<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Models\AppCenter\AppCenterApp;
use App\Models\Central\Mutual;
use App\Support\AndroidApp;
use App\Support\ApkInspector;
use App\Support\AppCenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Espace super-admin — APP CENTER MANAGEMENT (2026-11-13) :
 *  - activer / désactiver une application, textes, fonctionnalités, version, date, guide ;
 *  - icône (PNG carré), lien Google Play, APK officiel : refusé s'il n'est pas signé avec
 *    une clé déclarée dans « Application Android → Réglages » (empreintes SHA-256) ;
 *  - statistiques anonymes : intentions (clics) séparées des installations confirmées.
 */
class AppCenterAdminController extends Controller
{
    private const EDITORS = ['super_admin'];
    private const READERS = ['super_admin', 'moderator'];
    private const PERIODS = [7, 30, 90, 365];

    public function index(Request $request)
    {
        $this->allow($request, self::READERS);
        $days = in_array((int) $request->query('periode'), self::PERIODS, true) ? (int) $request->query('periode') : 30;
        $summary = AppCenter::summary($days);
        $mutualIds = array_keys($summary['mutuelle']['by_mutual'] ?? []);

        return view('network.appcenter.index', [
            'apps' => AppCenter::apps(),
            'summary' => $summary,
            'days' => $days,
            'periods' => self::PERIODS,
            'mutualNames' => $mutualIds ? Mutual::query()->whereIn('id', $mutualIds)->pluck('name', 'id')->all() : [],
            'canEdit' => $request->user()?->hasAnyRole(self::EDITORS),
        ]);
    }

    public function edit(Request $request, string $key)
    {
        $this->allow($request, self::EDITORS);
        $app = $this->app($key);

        return view('network.appcenter.edit', [
            'app' => $app,
            'fingerprints' => AndroidApp::fingerprints(),
        ]);
    }

    public function update(Request $request, string $key)
    {
        $this->allow($request, self::EDITORS);
        $app = $this->app($key);
        $d = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:2000'],
            'features' => ['nullable', 'string', 'max:2000'],
            'version' => [Rule::requiredIf(! $request->boolean('version_auto')), 'nullable', 'regex:/^[0-9A-Za-z][0-9A-Za-z.\-]{0,19}$/'],
            'released_on' => ['nullable', 'date', 'before_or_equal:today'],
            'guide_note' => ['nullable', 'string', 'max:600'],
            'play_store_url' => ['nullable', 'url', 'max:255', 'starts_with:https://play.google.com/'],
        ], [
            'version.regex' => 'Version : chiffres, lettres, points ou tirets (ex. 2026.11.13 ou 1.2.0).',
            'play_store_url.starts_with' => 'Le lien Google Play doit commencer par https://play.google.com/.',
        ]);
        $features = array_values(array_filter(array_map(fn ($l) => mb_substr(trim($l), 0, 90), preg_split('/\R/', (string) ($d['features'] ?? '')))));
        if (count($features) > 16) {
            return back()->withInput()->withErrors(['features' => 'Au plus 16 fonctionnalités (une par ligne).']);
        }
        $app->fill([
            'enabled' => $request->boolean('enabled'),
            'name' => $d['name'], 'tagline' => $d['tagline'] ?? null, 'description' => $d['description'],
            'features' => $features, 'version_auto' => $request->boolean('version_auto'),
            'version' => ($d['version'] ?? null) ?: $app->getRawOriginal('version'), 'released_on' => $d['released_on'] ?? null,
            'guide_note' => $d['guide_note'] ?? null, 'play_store_url' => $d['play_store_url'] ?? null,
        ])->save();
        AppCenter::forget();

        return redirect()->route('network.appcenter.edit', $key)->with('success', 'Application mise à jour.');
    }

    /** Icône carrée PNG (192 à 1024 px). */
    public function icon(Request $request, string $key)
    {
        $this->allow($request, self::EDITORS);
        $app = $this->app($key);
        if ($request->boolean('retirer')) {
            if ($app->icon_path) {
                Storage::disk('local')->delete($app->icon_path);
            }
            $app->update(['icon_path' => null]);
            AppCenter::forget();

            return back()->with('success', 'Icône retirée : l’icône MX-CONNECT par défaut est utilisée.');
        }
        $request->validate(['icone' => ['required', 'file', 'mimes:png', 'max:1024', 'dimensions:min_width=192,max_width=1024,ratio=1']],
            ['icone.dimensions' => 'Icône carrée de 192 à 1024 pixels attendue.', 'icone.mimes' => 'Format PNG attendu.']);
        $path = $request->file('icone')->storeAs('appcenter/icons', $key . '.png', 'local');
        $app->update(['icon_path' => $path]);
        AppCenter::forget();

        return back()->with('success', 'Icône enregistrée.');
    }

    /** APK officiel : signature vérifiée contre les empreintes déclarées. */
    public function apk(Request $request, string $key)
    {
        $this->allow($request, self::EDITORS);
        $app = $this->app($key);
        $request->validate([
            'apk' => ['required', 'file', 'max:51200'],
            'apk_version' => ['required', 'regex:/^[0-9A-Za-z][0-9A-Za-z.\- ()]{0,29}$/'],
        ], ['apk.max' => 'APK de 50 Mo au plus.', 'apk_version.regex' => 'Version : par exemple 0.2.0-beta (2).']);

        $file = $request->file('apk');
        if (strtolower($file->getClientOriginalExtension()) !== 'apk') {
            return back()->withErrors(['apk' => 'Fichier .apk attendu.']);
        }
        $official = AndroidApp::fingerprints();
        if (! $official) {
            return back()->withErrors(['apk' => 'Déclarez d’abord l’empreinte SHA-256 de la clé officielle dans « Application Android → Réglages ». Sans elle, aucun APK ne peut être vérifié.']);
        }
        try {
            $info = ApkInspector::inspect($file->getRealPath());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['apk' => $e->getMessage()]);
        }
        $match = array_values(array_intersect($info['fingerprints'], $official));
        if (! $match) {
            return back()->withErrors(['apk' => 'Signature refusée : cet APK n’est pas signé avec la clé officielle (empreinte trouvée : '
                . substr($info['fingerprints'][0], 0, 23) . '…). Un APK de débogage ou signé avec une autre clé n’est jamais publié.']);
        }

        $sha = hash_file('sha256', $file->getRealPath());
        $path = $file->storeAs('appcenter/apk', $key . '-' . substr($sha, 0, 12) . '.apk', 'local');
        if ($app->apk_path && $app->apk_path !== $path) {
            Storage::disk('local')->delete($app->apk_path);
        }
        $app->update([
            'apk_path' => $path, 'apk_version' => $request->input('apk_version'), 'apk_sha256' => $sha,
            'apk_signer' => $match[0], 'apk_size' => (int) $file->getSize(), 'apk_uploaded_at' => now(),
        ]);
        AppCenter::forget();

        return back()->with('success', 'APK vérifié (signature ' . $info['scheme'] . ' avec la clé officielle) et publié.');
    }

    public function removeApk(Request $request, string $key)
    {
        $this->allow($request, self::EDITORS);
        $app = $this->app($key);
        if ($app->apk_path) {
            Storage::disk('local')->delete($app->apk_path);
        }
        $app->update(['apk_path' => null, 'apk_version' => null, 'apk_sha256' => null, 'apk_signer' => null, 'apk_size' => null, 'apk_uploaded_at' => null]);
        AppCenter::forget();

        return back()->with('success', 'APK retiré : il n’est plus proposé au téléchargement.');
    }

    /** Ligne brute (version saisie, et non la version automatique affichée). */
    private function app(string $key): AppCenterApp
    {
        abort_unless(in_array($key, AppCenter::KEYS, true), 404);
        AppCenter::apps();   // crée les applications au premier accès

        return AppCenterApp::query()->where('key', $key)->firstOrFail();
    }

    private function allow(Request $request, array $roles): void
    {
        abort_unless($request->user()?->hasAnyRole($roles), 403);
    }
}
