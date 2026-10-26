<?php

namespace App\Http\Middleware;

use App\Support\TenantAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applique la matrice des droits (App\Support\TenantAccess) à chaque route du
 * back-office des mutuelles : une rubrique interdite à un rôle l'est aussi quand
 * on tape son adresse, pas seulement dans le menu.
 */
class TenantRouteAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = (string) $request->route()?->getName();
        if (! TenantAccess::can($route, $request->user('web'))) {
            abort(403, 'Cette rubrique n’est pas ouverte à votre rôle. Demandez à l’administrateur de la mutuelle si besoin.');
        }

        return $next($request);
    }
}
