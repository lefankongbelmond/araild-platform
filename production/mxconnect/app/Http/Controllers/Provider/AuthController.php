<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Models\Central\CareProvider;
use App\Models\Central\Country;
use App\Models\Provider\ProviderAccount;
use App\Services\Provider\ProviderSpaceService;
use App\Support\MailStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

/**
 * Comptes de l'espace prestataire central (garde « provider ») : connexion, inscription d'un
 * établissement, mot de passe oublié, mot de passe provisoire, double authentification TOTP
 * (même moteur que le personnel des mutuelles et le super-admin), paramètres du compte.
 */
class AuthController extends Controller
{
    public function showLogin()
    {
        return view('provider.auth.login', ['mailReady' => MailStatus::configured()]);
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']],
            ['email.required' => 'Indiquez votre adresse e-mail.', 'password.required' => 'Indiquez votre mot de passe.']);
        $data['email'] = Str::lower(trim($data['email']));

        if (! Auth::guard('provider')->attempt($data, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'Adresse e-mail ou mot de passe incorrect.']);
        }
        $account = Auth::guard('provider')->user();
        if (! $account->active) {
            Auth::guard('provider')->logout();
            throw ValidationException::withMessages(['email' => 'Ce compte a été désactivé. Contactez l’administrateur de votre établissement.']);
        }
        $request->session()->regenerate();
        $request->session()->put('provider_mfa_passed', false);
        $account->forceFill(['last_login_at' => now()])->saveQuietly();

        return redirect()->intended(route('pro.dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::guard('provider')->logout();
        $request->session()->forget(['provider_mfa_passed', 'provider_membership']);
        $request->session()->regenerateToken();

        return redirect()->route('pro.login')->with('success', 'Vous êtes déconnecté de l’espace prestataire.');
    }

    // --- Inscription d'un établissement (validation par une mutuelle) ---

    public function showRegister(ProviderSpaceService $space)
    {
        return view('provider.auth.register', [
            'countries' => Country::where('active', true)->orderBy('name')->get(),
            'mutuals'   => \App\Models\Central\Mutual::query()->operational()->where('status', '!=', 'suspended')->orderBy('name')->get(['id', 'name', 'city', 'country_id']),
            'types'     => CareProvider::TYPES,
        ]);
    }

    public function register(Request $request, ProviderSpaceService $space)
    {
        $data = $request->validate([
            'establishment'       => ['required', 'string', 'min:3', 'max:180'],
            'type'                => ['required', Rule::in(array_keys(CareProvider::TYPES))],
            'country_id'          => ['required', 'integer', Rule::exists('central.countries', 'id')->where('active', true)],
            'locality'            => ['required', 'string', 'max:120'],
            'approval_number'     => ['nullable', 'string', 'max:60'],
            'establishment_phone' => ['nullable', 'string', 'max:30'],
            'establishment_email' => ['nullable', 'email', 'max:180'],
            'first_name'          => ['required', 'string', 'max:120'],
            'last_name'           => ['required', 'string', 'max:120'],
            'email'               => ['required', 'email:rfc', 'max:180', Rule::unique('central.provider_accounts', 'email')],
            'phone'               => ['nullable', 'string', 'max:30'],
            'password'            => ['required', 'confirmed', PasswordRule::min(10)->letters()->numbers()],
            'mutuals'             => ['array', 'max:10'],
            'mutuals.*'           => ['string', 'max:64'],
            'message'             => ['nullable', 'string', 'max:1000'],
            'attest'              => ['accepted'],
        ], [
            'establishment.required' => 'Indiquez le nom de l’établissement.',
            'locality.required'      => 'Indiquez la localité de l’établissement.',
            'email.unique'           => 'Un compte existe déjà avec cette adresse : connectez-vous, ou demandez à l’administrateur de votre établissement de vous ajouter.',
            'attest.accepted'        => 'Vous devez attester que vous représentez cet établissement.',
            'password.confirmed'     => 'Les deux mots de passe ne correspondent pas.',
        ]);
        $data['email'] = Str::lower(trim($data['email']));
        [$account] = $space->register($data);

        Auth::guard('provider')->login($account);
        $request->session()->regenerate();
        $request->session()->put('provider_mfa_passed', false);

        return redirect()->route('pro.mfa.enroll')->with('success', 'Établissement inscrit. Activez la double authentification pour protéger votre compte. Vos demandes d’affiliation sont envoyées aux mutuelles choisies.');
    }

    // --- Mot de passe oublié ---

    public function forgot()
    {
        return view('provider.auth.forgot', ['mailReady' => MailStatus::configured()]);
    }

    public function sendReset(Request $request)
    {
        abort_unless(MailStatus::configured(), 503, 'L’envoi d’e-mails n’est pas encore configuré sur ce site : demandez à l’administrateur de votre établissement de réinitialiser votre mot de passe.');
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:180']]);
        Password::broker('provider_accounts')->sendResetLink(['email' => Str::lower(trim($data['email']))]);

        return back()->with('success', 'Si un compte correspond à cette adresse, un e-mail de réinitialisation vient d’être envoyé. Pensez à regarder dans les courriers indésirables.');
    }

    public function showReset(Request $request, string $token)
    {
        return view('provider.auth.reset', ['token' => $token, 'email' => (string) $request->query('email')]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'], 'email' => ['required', 'email:rfc'],
            'password' => ['required', 'confirmed', PasswordRule::min(10)->letters()->numbers()],
        ]);
        $data['email'] = Str::lower(trim($data['email']));
        $status = Password::broker('provider_accounts')->reset($data, function (ProviderAccount $a, string $password) {
            $a->forceFill(['password' => $password, 'must_change_password' => false, 'remember_token' => Str::random(60)])->save();
        });
        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput(['email' => $data['email']])->withErrors(['email' => 'Ce lien n’est plus valable (expiré ou déjà utilisé). Refaites une demande.']);
        }

        return redirect()->route('pro.login')->with('success', 'Mot de passe modifié. Vous pouvez vous connecter.');
    }

    // --- Mot de passe provisoire (compte créé par l'administrateur de l'établissement ou par une mutuelle) ---

    public function showPassword(Request $request)
    {
        return view('provider.auth.password', ['account' => $request->user('provider')]);
    }

    public function updatePassword(Request $request)
    {
        $account = $request->user('provider');
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'confirmed', PasswordRule::min(10)->letters()->numbers()],
        ], ['password.confirmed' => 'Les deux mots de passe ne correspondent pas.']);
        if (! Hash::check($data['current_password'], $account->password)) {
            return back()->withErrors(['current_password' => 'Mot de passe actuel incorrect.']);
        }
        if (Hash::check($data['password'], $account->password)) {
            return back()->withErrors(['password' => 'Choisissez un mot de passe différent du mot de passe provisoire.']);
        }
        $account->forceFill(['password' => $data['password'], 'must_change_password' => false])->save();

        return redirect()->route('pro.dashboard')->with('success', 'Mot de passe enregistré.');
    }

    // --- Double authentification (TOTP) ---

    public function enroll(Request $request, Google2FA $g2fa)
    {
        $account = $request->user('provider');
        if ($account->mfa_enabled) {
            return redirect()->route('pro.mfa.challenge');
        }
        if (empty($account->mfa_secret)) {
            $account->forceFill(['mfa_secret' => $g2fa->generateSecretKey()])->save();
        }

        return view('provider.auth.mfa', ['mode' => 'enroll', 'qrUrl' => $g2fa->getQRCodeUrl('MX-CONNECT Prestataire', $account->email, $account->mfa_secret)]);
    }

    public function confirm(Request $request, Google2FA $g2fa)
    {
        $request->validate(['code' => ['required', 'digits:6']], ['code.digits' => 'Le code comporte 6 chiffres.']);
        $account = $request->user('provider');
        if ($account->mfa_enabled || ! $account->mfa_secret || ! $g2fa->verifyKey($account->mfa_secret, $request->code)) {
            return back()->withErrors(['code' => 'Code incorrect. Vérifiez l’heure de votre téléphone et réessayez.']);
        }
        $account->forceFill(['mfa_enabled' => true])->save();
        $request->session()->put('provider_mfa_passed', true);

        return redirect()->route('pro.dashboard')->with('success', 'Double authentification activée.');
    }

    public function challenge(Request $request)
    {
        if (! $request->user('provider')->mfa_enabled) {
            return redirect()->route('pro.mfa.enroll');
        }

        return view('provider.auth.mfa', ['mode' => 'challenge']);
    }

    public function verify(Request $request, Google2FA $g2fa)
    {
        $request->validate(['code' => ['required', 'digits:6']], ['code.digits' => 'Le code comporte 6 chiffres.']);
        $account = $request->user('provider');
        if (! $account->mfa_enabled || ! $g2fa->verifyKey($account->mfa_secret, $request->code)) {
            return back()->withErrors(['code' => 'Code incorrect. Vérifiez l’heure de votre téléphone et réessayez.']);
        }
        $request->session()->put('provider_mfa_passed', true);

        return redirect()->intended(route('pro.dashboard'));
    }

    // --- Paramètres du compte ---

    public function settings(Request $request)
    {
        return view('provider.settings', ['account' => $request->user('provider')]);
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:120'], 'last_name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);
        $request->user('provider')->update($data);

        return back()->with('success', 'Vos informations sont enregistrées.');
    }

    public function changePassword(Request $request)
    {
        $account = $request->user('provider');
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'confirmed', PasswordRule::min(10)->letters()->numbers()],
        ], ['password.confirmed' => 'Les deux mots de passe ne correspondent pas.']);
        if (! Hash::check($data['current_password'], $account->password)) {
            return back()->withErrors(['current_password' => 'Mot de passe actuel incorrect.']);
        }
        $account->forceFill(['password' => $data['password'], 'remember_token' => Str::random(60)])->save();

        return back()->with('success', 'Mot de passe modifié.');
    }
}
