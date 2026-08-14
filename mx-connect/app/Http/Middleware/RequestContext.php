<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Request correlation & log context (Phase 3 observability).
 *
 * Assigns a request id (honouring an inbound X-Request-Id from an upstream proxy),
 * pushes it — with the current tenant and authenticated user — into the shared log
 * context so every line emitted during the request is correlatable, and echoes the
 * id back on the response header for client/proxy tracing.
 */
class RequestContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->headers->get('X-Request-Id') ?: (string) Str::uuid();

        Log::withContext([
            'request_id' => $requestId,
            'tenant'     => function_exists('tenant') && tenant() ? tenant('id') : null,
            'user_id'    => optional($request->user())->getAuthIdentifier(),
            'ip'         => $request->ip(),
        ]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
