<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\Central\PublicAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Web sign-in for public accounts (guard `member`) on the central domain — the
 * same `public_accounts` identity the member PWA already uses, so a person keeps
 * one account across the social network and their mutual(s).
 */
class AuthController extends Controller
{
    public function showLogin()
    {
        return view('social.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('member')->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['phone' => __('mxconnect.auth.failed')]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('feed'));
    }

    public function showRegister()
    {
        return view('social.auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9]{8,15}$/', Rule::unique(PublicAccount::class, 'phone')],
            'email' => ['nullable', 'email', 'max:180'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'terms' => ['accepted'],
        ]);

        $account = PublicAccount::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'password' => $data['password'],
        ]);

        Auth::guard('member')->login($account);
        $request->session()->regenerate();

        return redirect()->route('feed')->with('success', __('mxconnect.social.auth.welcome', ['name' => $account->first_name]));
    }

    public function logout(Request $request)
    {
        Auth::guard('member')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
