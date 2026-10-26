<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

/**
 * Connexion du personnel d'une mutuelle (guard 'web', base TENANT) + second
 * facteur TOTP. Servi uniquement sur <slug>.mx-connect.com.
 * Le secret TOTP est stocké chiffré dans users.two_factor_secret.
 */
class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::guard('web')->check()) {
            return redirect()->route('members.index');
        }

        return view('tenant.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => __('mxconnect.auth.failed')]);
        }

        if (! Auth::guard('web')->user()->active) {
            Auth::guard('web')->logout();
            throw ValidationException::withMessages(['email' => __('mxconnect.auth.inactive')]);
        }

        $request->session()->regenerate();
        $request->session()->put('mfa_passed', false);

        return redirect()->intended(route('members.index'));
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('tenant.login');
    }

    public function enroll(Request $request, Google2FA $g2fa)
    {
        $user = $request->user('web');
        if (empty($user->two_factor_secret)) {
            $user->two_factor_secret = encrypt($g2fa->generateSecretKey());
            $user->save();
        }
        $qrUrl = $g2fa->getQRCodeUrl(
            config('app.name') . ' · ' . (tenant('name') ?? ''),
            $user->email,
            decrypt($user->two_factor_secret)
        );

        return view('tenant.auth.mfa', ['mode' => 'enroll', 'qrUrl' => $qrUrl]);
    }

    public function confirm(Request $request, Google2FA $g2fa)
    {
        $request->validate(['code' => ['required', 'digits:6']]);
        $user = $request->user('web');

        if (! $g2fa->verifyKey(decrypt($user->two_factor_secret), $request->code)) {
            return back()->withErrors(['code' => __('mxconnect.mfa.invalid_code')]);
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        $request->session()->put('mfa_passed', true);

        return redirect()->intended(route('members.index'));
    }

    public function challenge()
    {
        return view('tenant.auth.mfa', ['mode' => 'challenge']);
    }

    public function verify(Request $request, Google2FA $g2fa)
    {
        $request->validate(['code' => ['required', 'digits:6']]);
        $user = $request->user('web');

        if (! $g2fa->verifyKey(decrypt($user->two_factor_secret), $request->code)) {
            return back()->withErrors(['code' => __('mxconnect.mfa.invalid_code')]);
        }

        $request->session()->put('mfa_passed', true);

        return redirect()->intended(route('members.index'));
    }
}
