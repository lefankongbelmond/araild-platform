<?php

namespace App\Http\Middleware;

use App\Support\SocialLoad;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Surveille la charge du réseau social et applique le mode allégé (voir SocialLoad).
 * Pages mises en cache (visiteurs non connectés, mode allégé uniquement) : fils publics
 * sans formulaire d'envoi, pour 30 secondes.
 */
class SocialLoadGuard
{
    private const CACHEABLE = ['fil', 'publications', 'explorer', 'reels', 'evenements'];

    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);
        $light = SocialLoad::light();
        if ($light) {
            config(['mxconnect.social.per_page' => 8, 'mxconnect.social.link_preview' => false]);
        }
        $request->attributes->set('social_light', $light);

        $key = null;
        if ($light && $request->isMethod('GET') && ! auth('member')->check()
            && in_array(trim($request->path(), '/'), self::CACHEABLE, true) && ! $request->has('fragment') && ! $request->has('cursor')) {
            $key = 'social:page:' . sha1($request->fullUrl());
            if ($html = Cache::get($key)) {
                return response($html)->header('Content-Type', 'text/html; charset=UTF-8')->header('X-MX-Cache', 'hit');
            }
        }

        $response = $next($request);

        if ($key && $response->getStatusCode() === 200 && method_exists($response, 'getContent')) {
            Cache::put($key, $response->getContent(), 30);
        }
        SocialLoad::record((microtime(true) - $start) * 1000);

        return $response;
    }
}
