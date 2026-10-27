<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\Central\PublicAccount;
use App\Services\Social\LegalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Comptes grand public du réseau (table public_accounts existante, garde « member »).
 * Le même compte sert ensuite à l'espace adhérent des mutuelles (PWA) : aucune
 * nouvelle table d'utilisateurs n'est créée.
 */
class AuthController extends Controller
{
    public function showRegister()
    {
        return view('social.auth.register');
    }

    public function register(Request $request)
    {
        // Application : indicatif choisi + numéro local (maquette 4, téléphone facultatif).
        if ($request->filled('phone_local')) {
            $request->merge(['phone' => $request->input('phone_cc', '+237') . preg_replace('/\D+/', '', (string) $request->input('phone_local'))]);
        }
        $request->merge([
            'phone' => self::normalizePhone($request->input('phone')),
            'email' => $request->filled('email') ? Str::lower(trim($request->input('email'))) : null,
        ]);
        // Documents à cocher dans le formulaire (2026-11-09) : l'application présente chaque
        // document publié (conditions, confidentialité) avec sa propre case, jamais pré-cochée ;
        // le formulaire web garde sa case unique (règles communautaires + confidentialité).
        $legal = app(LegalService::class);
        $app = $request->input('app') === '1';
        $shown = $app ? $legal->current()->whereIn('type', ['terms', 'privacy'])->values() : $legal->current()->whereIn('type', ['community', 'privacy'])->values();
        $docRules = $app ? $shown->mapWithKeys(fn ($d) => ['doc_' . $d->id => ['accepted']])->all() : ['terms' => ['accepted']];
        $docMessages = $app ? $shown->mapWithKeys(fn ($d) => ['doc_' . $d->id . '.accepted' => 'Cochez « ' . $d->checkboxText() . ' » pour créer votre compte.'])->all() : [];

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name'  => ['required', 'string', 'max:120'],
            // Téléphone OU e-mail (maquette Android : téléphone facultatif si l'e-mail est donné).
            'phone'      => ['nullable', 'required_without:email', 'regex:/^\+?[0-9]{8,15}$/', Rule::unique('central.public_accounts', 'phone')],
            'email'      => ['nullable', 'required_without:phone', 'email:rfc', 'max:180', Rule::unique('central.public_accounts', 'email')],
            'password'   => ['required', ...($request->has('password_confirmation') ? ['confirmed'] : []), Password::min(8)->letters()->numbers()],
        ] + $docRules, [
            'phone.regex'    => 'Numéro de téléphone invalide (8 à 15 chiffres, indicatif possible : +225…).',
            'phone.unique'   => 'Un compte existe déjà avec ce numéro. Connectez-vous.',
            'phone.required_without' => 'Indiquez un numéro de téléphone ou une adresse e-mail.',
            'email.required_without' => 'Indiquez une adresse e-mail ou un numéro de téléphone.',
            'email.unique'   => 'Un compte existe déjà avec cette adresse e-mail.',
            'terms.accepted' => 'Vous devez accepter les conditions d’utilisation.',
        ] + $docMessages);

        $account = PublicAccount::create([
            'first_name' => trim($data['first_name']),
            'last_name'  => trim($data['last_name']),
            'phone'      => $data['phone'] ?? null,
            'email'      => $data['email'] ?? null,
            'password'   => $data['password'],   // haché par le cast « hashed »
        ]);

        $legal->record($account, $shown, $request);   // acceptations tracées (version, date, canal, IP)

        Auth::guard('member')->login($account, $app);   // application : session conservée sur l'appareil
        $request->session()->regenerate();

        // Documents encore dus (ex. règles communautaires dans l'application) : écran « Conditions ».
        $after = $app ? route('social.app.ready') : route('social.mutuals');
        if ($legal->pendingFor($account)->isNotEmpty()) {
            return redirect()->route('social.legal.pending', ['app' => $app ? 1 : null, 'suite' => parse_url($after, PHP_URL_PATH)]);
        }
        if ($app) {
            return redirect($after);
        }

        return redirect()->route('social.mutuals')
            ->with('ok', 'Bienvenue sur MX Connect, ' . $account->first_name . ' ! Suivez quelques mutuelles pour personnaliser votre fil.');
    }

    public function showLogin()
    {
        return view('social.auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'login'    => ['required', 'string', 'max:180'],
            'password' => ['required', 'string', 'max:255'],
        ], [], ['login' => 'téléphone ou e-mail']);

        $key = 'social-login:' . Str::lower($data['login']) . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['login' => 'Trop de tentatives. Réessayez dans ' . RateLimiter::availableIn($key) . ' secondes.']);
        }

        $login = trim($data['login']);
        $account = str_contains($login, '@')
            ? PublicAccount::where('email', Str::lower($login))->first()
            : PublicAccount::whereIn('phone', array_unique([self::normalizePhone($login), $login]))->first();

        if (! $account || ! Hash::check($data['password'], $account->password)) {
            RateLimiter::hit($key, 300);
            throw ValidationException::withMessages(['login' => 'Identifiants incorrects.']);
        }
        RateLimiter::clear($key);
        if ($account->isSuspended()) {
            throw ValidationException::withMessages(['login' => 'Ce compte est suspendu par la modération du réseau.' . ($account->suspension_reason ? ' Motif : ' . $account->suspension_reason : '')]);
        }

        Auth::guard('member')->login($account, $request->boolean('remember'));
        $request->session()->regenerate();

        // Nouvelle version d'un document à valider : écran « Conditions » d'abord (2026-11-09).
        if (app(LegalService::class)->pendingFor($account)->isNotEmpty()) {
            return redirect()->route('social.legal.pending', ['app' => $request->input('app') === '1' ? 1 : null]);
        }

        return redirect()->intended(route('social.home'));
    }

    public function logout(Request $request)
    {
        Auth::guard('member')->logout();
        $request->session()->regenerateToken();

        return redirect('/')->with('ok', 'Vous êtes déconnecté.');
    }

    /** « +225 07 00 00 00 00 » -> « +2250700000000 » ; « 00225… » -> « +225… ». */
    public static function normalizePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }
        $p = preg_replace('/[\s.\-()]/', '', trim($phone));
        if (str_starts_with($p, '00')) {
            $p = '+' . substr($p, 2);
        }

        return $p;
    }
}
