<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\Central\PublicAccount;
use App\Support\MailStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * « Mot de passe oublié » des membres, par e-mail. La réponse est toujours la même,
 * que l'adresse existe ou non (on ne révèle pas qui est inscrit). Si l'envoi
 * d'e-mails n'est pas configuré sur le site, on le dit honnêtement.
 */
class PasswordResetController extends Controller
{
    public function request()
    {
        return view('social.auth.forgot', ['mailReady' => MailStatus::configured()]);
    }

    public function email(Request $request)
    {
        abort_unless(MailStatus::configured(), 503, 'L’envoi d’e-mails n’est pas encore configuré sur ce site.');
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:180']]);
        Password::broker('public_accounts')->sendResetLink(['email' => Str::lower(trim($data['email']))]);

        return back()->with('ok', 'Si un compte correspond à cette adresse, un e-mail de réinitialisation vient d’être envoyé. Pensez à regarder dans les courriers indésirables.');
    }

    public function edit(Request $request, string $token)
    {
        return view('social.auth.reset', ['token' => $token, 'email' => (string) $request->query('email')]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'token'    => ['required', 'string'],
            'email'    => ['required', 'email:rfc'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);
        $data['email'] = Str::lower(trim($data['email']));

        $status = Password::broker('public_accounts')->reset($data, function (PublicAccount $account, string $password) {
            $account->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput(['email' => $data['email']])
                ->withErrors(['email' => 'Ce lien n’est plus valable (expiré ou déjà utilisé). Refaites une demande.']);
        }

        return redirect()->route('social.login')->with('ok', 'Mot de passe modifié. Vous pouvez vous connecter.');
    }
}
