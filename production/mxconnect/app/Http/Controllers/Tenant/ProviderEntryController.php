<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Provider\ProviderSpaceRefusal;
use App\Services\Provider\ProviderSpaceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Entrée dans le portail de la mutuelle depuis l'espace prestataire central (2026-11-16) :
 * jeton à usage unique, valable 60 secondes, propre à cette mutuelle.
 */
class ProviderEntryController extends Controller
{
    public function __invoke(Request $request, string $token, ProviderSpaceService $space)
    {
        // Une session déjà ouverte sur ce sous-domaine (autre personne, personnel…) est fermée d'abord.
        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
        try {
            $route = $space->enter($token, $request);
        } catch (ProviderSpaceRefusal $e) {
            return response()->view('tenant.portal.entry-refused', [
                'message' => $e->getMessage(), 'back' => rtrim((string) config('app.url'), '/') . '/espace-prestataire',
            ], 403)->header('Referrer-Policy', 'no-referrer');
        }

        // Redirection immédiate : le jeton ne reste ni dans l'historique ni dans un en-tête Referer.
        return redirect()->route($route)->header('Referrer-Policy', 'no-referrer');
    }
}
