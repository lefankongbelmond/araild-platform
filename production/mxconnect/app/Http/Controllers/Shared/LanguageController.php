<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Support\Locales;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Schema;

/**
 * Choix de la langue de l'interface (M1 multilingue, 2026-11-27). Lien simple (GET, aucune
 * donnée modifiée hors la préférence) : cookie d'un an + préférence enregistrée sur le(s)
 * compte(s) connecté(s) de ce navigateur, pour la retrouver sur un autre appareil.
 */
class LanguageController extends Controller
{
    public function __invoke(Request $request, string $code)
    {
        abort_unless(Locales::isActive($code), 404);

        $guards = function_exists('tenancy') && tenancy()->initialized ? ['web'] : ['member', 'provider', 'network'];
        foreach ($guards as $guard) {
            try {
                $user = auth($guard)->user();
                if ($user && Schema::connection($user->getConnectionName())->hasColumn($user->getTable(), 'locale') && ($user->getAttributes()['locale'] ?? null) !== $code) {
                    $user->forceFill(['locale' => $code])->saveQuietly();
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }
        Cookie::queue(Cookie::make(Locales::COOKIE, $code, 60 * 24 * 365, '/', null, $request->isSecure(), true, false, 'lax'));

        // Retour à la page d'où vient le choix, si elle est sur le même site.
        $back = (string) $request->headers->get('referer', '');
        $target = $back !== '' && parse_url($back, PHP_URL_HOST) === $request->getHost() ? $back : '/';

        return redirect()->to($target);
    }
}
