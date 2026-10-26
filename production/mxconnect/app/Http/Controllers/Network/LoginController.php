<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/** Network back-office login (super admin / security / moderator) — 'network' guard. */
class LoginController extends Controller
{
    public function show()
    {
        return view('network.auth.login');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Rate limiting is applied via the 'throttle' middleware on the route.
        if (! Auth::guard('network')->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('mxconnect.auth.failed'),
            ]);
        }

        if (! Auth::guard('network')->user()->active) {
            Auth::guard('network')->logout();
            throw ValidationException::withMessages(['email' => __('mxconnect.auth.inactive')]);
        }

        $request->session()->regenerate();
        $request->session()->put('mfa_passed', false); // force MFA challenge next

        return redirect()->intended(route('network.mutuals.index'));
    }

    public function destroy(Request $request)
    {
        Auth::guard('network')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('network.login');
    }
}
