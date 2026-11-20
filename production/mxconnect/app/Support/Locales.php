<?php

namespace App\Support;

use App\Models\Central\Locale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Langues de l'interface (M1 multilingue, 2026-11-27).
 *
 * Source unique : quelles langues l'application sait afficher (fichiers de langue livrés), leur
 * nom dans leur propre langue, leur sens d'écriture, leurs séparateurs de nombres, et la langue à
 * appliquer pour une requête. Le français est la langue source : un texte non traduit s'affiche en
 * français (fallback_locale), jamais une clé technique.
 *
 * Ordre de choix : 1. le compte connecté (membre, personnel, prestataire, super-admin) ;
 * 2. le cookie « mx_lang » (choix fait avant connexion) ; 3. la langue de la mutuelle ;
 * 4. la langue du navigateur ; 5. le français. Seules les langues ACTIVÉES par le super-admin
 * (table centrale `locales`) sont retenues ; le français l'est toujours.
 */
class Locales
{
    public const COOKIE = 'mx_lang';
    public const SOURCE = 'fr';

    /** Langues livrées : code => [nom natif, sens, séparateur décimal, séparateur des milliers]. */
    public const SUPPORTED = [
        'fr' => ['Français', 'ltr', ',', ' '],
        'en' => ['English', 'ltr', '.', ','],
        'pt' => ['Português', 'ltr', ',', '.'],
        'es' => ['Español', 'ltr', ',', '.'],
        'ar' => ['العربية', 'rtl', '.', ','],
        'sw' => ['Kiswahili', 'ltr', '.', ','],
        'ha' => ['Hausa', 'ltr', '.', ','],
    ];

    private static ?array $active = null;

    public static function supported(): array
    {
        return array_keys(self::SUPPORTED);
    }

    public static function isSupported(?string $code): bool
    {
        return $code !== null && isset(self::SUPPORTED[$code]);
    }

    /** Langues proposées : livrées ET activées par le super-admin (le français toujours). */
    public static function active(): array
    {
        if (self::$active !== null) {
            return self::$active;
        }
        try {
            $codes = Cache::remember('mx.locales.active', 600, fn () => Locale::query()->where('active', true)->pluck('code')->all());
        } catch (\Throwable) {
            $codes = [];
        }
        $list = array_values(array_filter(self::supported(), fn ($c) => $c === self::SOURCE || in_array($c, $codes, true)));

        return self::$active = $list;
    }

    /** Début de requête : relire la liste (le cache applicatif reste). */
    public static function resetRequestState(): void
    {
        self::$active = null;
    }

    public static function forgetCache(): void
    {
        self::$active = null;
        Cache::forget('mx.locales.active');
    }

    public static function isActive(?string $code): bool
    {
        return $code !== null && in_array($code, self::active(), true);
    }

    public static function name(string $code): string
    {
        return self::SUPPORTED[$code][0] ?? $code;
    }

    public static function direction(?string $code = null): string
    {
        return self::SUPPORTED[$code ?? app()->getLocale()][1] ?? 'ltr';
    }

    public static function isRtl(?string $code = null): bool
    {
        return self::direction($code) === 'rtl';
    }

    /** [séparateur décimal, séparateur des milliers] de la langue en cours. */
    public static function separators(?string $code = null): array
    {
        $s = self::SUPPORTED[$code ?? app()->getLocale()] ?? self::SUPPORTED[self::SOURCE];

        return [$s[2], $s[3]];
    }

    /** Nombre formaté selon la langue en cours (aucune conversion de devise). */
    public static function number(float|int $value, int $decimals = 0): string
    {
        [$dec, $thousands] = self::separators();

        return number_format($value, $decimals, $dec, $thousands);
    }

    /** Attributs de <html> : lang et dir de la langue en cours. */
    public static function htmlAttributes(): string
    {
        $code = app()->getLocale();

        return 'lang="' . e(str_replace('_', '-', $code)) . '" dir="' . self::direction($code) . '"';
    }

    /** Langue du navigateur (Accept-Language) parmi les langues actives. */
    public static function fromBrowser(Request $request): ?string
    {
        foreach ($request->getLanguages() as $lang) {
            $code = strtolower(substr(str_replace('-', '_', $lang), 0, 2));
            if (self::isActive($code)) {
                return $code;
            }
        }

        return null;
    }

    /** Applique la langue : traductions, dates (Carbon). */
    public static function apply(string $code): void
    {
        app()->setLocale($code);
        \Illuminate\Support\Carbon::setLocale($code);
        \Carbon\Carbon::setLocale($code);
    }

    /**
     * Langues dont TOUTE l'interface est traduite (déclaré à chaque livraison, voir le plan
     * multilingue). Les autres sont signalées « traduction partielle » dans le sélecteur.
     */
    public const COMPLETE = [];

    public static function isComplete(string $code): bool
    {
        return $code === self::SOURCE || in_array($code, self::COMPLETE, true);
    }

    /**
     * Taux de traduction d'une langue, sur les textes DÉJÀ traduisibles : catalogue JSON
     * (lang/fr.json) et fichiers de groupe (lang/fr/*.php). null pour le français.
     */
    public static function completeness(string $code): ?int
    {
        if ($code === self::SOURCE) {
            return null;
        }
        $source = self::entries(self::SOURCE);
        if (! $source) {
            return 0;
        }
        $target = self::entries($code);
        $done = count(array_filter(array_keys($source), fn ($k) => isset($target[$k]) && $target[$k] !== ''));

        return (int) floor(100 * $done / count($source));
    }

    /** Toutes les entrées d'une langue : JSON + groupes PHP aplatis (« groupe.clé »). */
    public static function entries(string $code): array
    {
        static $cache = [];
        if (isset($cache[$code])) {
            return $cache[$code];
        }
        $out = self::catalog($code);
        foreach (glob(lang_path($code . '/*.php')) ?: [] as $file) {
            $data = require $file;
            if (is_array($data)) {
                foreach (\Illuminate\Support\Arr::dot($data) as $k => $v) {
                    if (is_string($v)) {
                        $out[basename($file, '.php') . '.' . $k] = $v;
                    }
                }
            }
        }

        return $cache[$code] = $out;
    }

    /** Contenu d'un fichier JSON de langue. */
    public static function catalog(string $code): array
    {
        $path = lang_path($code . '.json');
        if (! is_file($path)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }
}
