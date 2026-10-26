<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers (Phase 3). HSTS is only meaningful over HTTPS, so it
 * is emitted when the request is secure. The CSP is intentionally moderate — it
 * permits the Tailwind CDN and the inline config/bootstyle the views rely on —
 * and can be tightened per deployment via config('mxconnect.security.csp').
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Content-Type-Options'  => 'nosniff',
            'X-Frame-Options'         => 'DENY',
            'Referrer-Policy'         => 'strict-origin-when-cross-origin',
            'Permissions-Policy'      => 'camera=(), microphone=(), geolocation=()',
            'Content-Security-Policy' => $this->csp(),
        ];

        if ($request->secure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $key => $value) {
            if ($value !== null && ! $response->headers->has($key)) {
                $response->headers->set($key, $value);
            }
        }

        return $response;
    }

    /**
     * Les feuilles de style et polices du back-office sont servies par le domaine
     * principal (APP_URL) : on l'autorise aussi depuis les sous-domaines des mutuelles.
     */
    private function csp(): ?string
    {
        $csp = config('mxconnect.security.csp');
        $url = parse_url((string) config('app.url'));
        if (! $csp || empty($url['host'])) {
            return $csp;
        }
        $origin = ($url['scheme'] ?? 'https') . '://' . $url['host'] . (isset($url['port']) ? ':' . $url['port'] : '');

        return str_replace(["style-src 'self'", "font-src 'self'"], ["style-src 'self' {$origin}", "font-src 'self' {$origin}"], $csp);
    }
}
