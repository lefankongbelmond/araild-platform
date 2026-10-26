<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Le réseau social est servi sur le domaine central uniquement. Sur un
 * sous-domaine de mutuelle (espace de gestion), on renvoie vers le domaine
 * central pour éviter deux adresses pour la même page (et les cookies croisés).
 */
class CentralDomainOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        if (in_array($host, config('tenancy.central_domains', []), true)) {
            return $next($request);
        }
        $app = rtrim((string) config('app.url'), '/');
        $appHost = parse_url($app, PHP_URL_HOST);
        if ($appHost && $appHost !== $host) {
            return redirect()->away($app . $request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
