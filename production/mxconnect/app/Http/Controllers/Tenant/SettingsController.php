<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Central\Locale;
use App\Models\Tenant\MutualSetting;
use App\Support\Locales;
use App\Support\MutualIdentity;
use App\Support\TenantSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Paramètres de la mutuelle (multilingue M3a, 2026-11-30) : page d'accueil en cartes et fiche
 * « Identité et langue » (administrateur seul, règle settings.identity* de TenantAccess).
 */
class SettingsController extends Controller
{
    public function index()
    {
        return view('tenant.settings.index', ['sections' => TenantSettings::visible()]);
    }

    public function identity()
    {
        $mutual = tenant();

        return view('tenant.settings.identity', [
            'mutual'   => $mutual,
            'locales'  => $this->choices(),
            'current'  => \App\Http\Middleware\SetLocale::localeCode((int) ($mutual->locale_id ?? 0)),
            'contacts' => MutualIdentity::contacts(),
        ]);
    }

    public function updateIdentity(Request $request)
    {
        $codes = array_keys($this->choices());
        $d = $request->validate([
            'locale'  => ['required', 'string', 'in:' . implode(',', $codes)],
            'phone'   => ['nullable', 'string', 'max:40', 'regex:/^[0-9+().\s\-]{6,40}$/'],
            'email'   => ['nullable', 'email', 'max:180'],
            'address' => ['nullable', 'string', 'max:255'],
        ], [
            'locale.in'   => __('Choisissez une langue proposée par la plateforme.'),
            'phone.regex' => __('Numéro de téléphone invalide.'),
        ]);

        $mutual = tenant();
        $before = ['locale_id' => $mutual->locale_id] + MutualIdentity::contacts();

        // Langue par défaut : colonne centrale de la mutuelle (lue par SetTenantLocale pour son personnel).
        $localeId = Locale::query()->where('code', $d['locale'])->value('id');
        if ($localeId && (int) $mutual->locale_id !== (int) $localeId) {
            $mutual->locale_id = $localeId;
            $mutual->save();
            Cache::forget('mx.locale.code.' . $localeId);
        }
        // Coordonnées : base de la mutuelle.
        foreach (MutualIdentity::KEYS as $field => $key) {
            MutualSetting::put($key, isset($d[$field]) ? trim((string) $d[$field]) : null, $request->user()->id);
        }

        try {
            \Illuminate\Support\Facades\DB::table('audit_logs')->insert([
                'user_id' => $request->user()->id, 'action' => 'identity_updated', 'object_type' => 'mutual_identity',
                'before' => json_encode($before, JSON_UNESCAPED_UNICODE),
                'after' => json_encode(['locale_id' => $localeId] + MutualIdentity::contacts(), JSON_UNESCAPED_UNICODE),
                'ip' => $request->ip(), 'occurred_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);   // la trace ne doit pas empêcher l'enregistrement
        }

        return redirect()->route('settings.identity')->with('success', __('Identité et langue enregistrées.'));
    }

    /** Langues proposées : celles que la plateforme a activées (le français toujours). */
    private function choices(): array
    {
        $out = [];
        foreach (Locales::active() as $code) {
            $out[$code] = Locales::name($code);
        }

        return $out;
    }
}
