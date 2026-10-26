<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Déconnecte un membre suspendu par la modération (s'il était déjà connecté). */
class SocialActiveMember
{
    public function handle(Request $request, Closure $next): Response
    {
        $member = Auth::guard('member')->user();
        if ($member && $member->isSuspended()) {
            Auth::guard('member')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('social.login')->withErrors(['login' => 'Ce compte est suspendu par la modération du réseau.']);
        }

        return $next($request);
    }
}
