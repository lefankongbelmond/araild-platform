<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP second factor for the network guard. Uses pragmarx/google2fa (the same
 * engine Fortify uses). Enrolment stores an encrypted secret; challenge verifies a code.
 */
class MfaController extends Controller
{
    public function enroll(Request $request, Google2FA $g2fa)
    {
        $user = $request->user('network');
        if (empty($user->mfa_secret)) {
            $user->mfa_secret = $g2fa->generateSecretKey();
            $user->save();
        }
        $qrUrl = $g2fa->getQRCodeUrl(config('app.name'), $user->email, $user->mfa_secret);

        return view('network.auth.mfa', ['mode' => 'enroll', 'qrUrl' => $qrUrl]);
    }

    public function confirm(Request $request, Google2FA $g2fa)
    {
        $request->validate(['code' => ['required', 'digits:6']]);
        $user = $request->user('network');

        if (! $g2fa->verifyKey($user->mfa_secret, $request->code)) {
            return back()->withErrors(['code' => __('mxconnect.mfa.invalid_code')]);
        }

        $user->update(['mfa_enabled' => true]);
        $request->session()->put('mfa_passed', true);

        return redirect()->route('network.mutuals.index');
    }

    public function challenge()
    {
        return view('network.auth.mfa', ['mode' => 'challenge']);
    }

    public function verify(Request $request, Google2FA $g2fa)
    {
        $request->validate(['code' => ['required', 'digits:6']]);
        $user = $request->user('network');

        if (! $g2fa->verifyKey($user->mfa_secret, $request->code)) {
            return back()->withErrors(['code' => __('mxconnect.mfa.invalid_code')]);
        }

        $request->session()->put('mfa_passed', true);

        return redirect()->intended(route('network.mutuals.index'));
    }
}
