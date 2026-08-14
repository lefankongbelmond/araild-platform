<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forces multi-factor authentication for sensitive roles (config mxconnect.mfa_required_roles).
 *
 * Two conditions must hold for a sensitive-role user:
 *   1. they have MFA configured (two_factor_confirmed_at / mfa_enabled), and
 *   2. they have passed the second factor in THIS session (session flag 'mfa_passed').
 *
 * Works for both the 'network' and 'web' (tenant) guards.
 */
class EnsureMfaEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();               // resolves the active guard's user
        if (! $user) {
            return $next($request);              // unauthenticated: let auth middleware handle it
        }

        $required = config('mxconnect.mfa_required_roles', []);
        $isSensitive = method_exists($user, 'hasAnyRole') && $user->hasAnyRole($required);

        if (! $isSensitive) {
            return $next($request);
        }

        $mfaConfigured = ! empty($user->two_factor_confirmed_at) || ($user->mfa_enabled ?? false);

        if (! $mfaConfigured) {
            return redirect()->route('mfa.enroll')
                ->with('warning', __('mxconnect.mfa.enroll_required'));
        }

        if (! $request->session()->get('mfa_passed', false)) {
            return redirect()->route('mfa.challenge')
                ->with('info', __('mxconnect.mfa.challenge_required'));
        }

        return $next($request);
    }
}
