<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Services\Social\LegalService;
use App\Support\AndroidApp;
use Illuminate\Http\Request;

/**
 * Parcours de l'application Android (TWA, 2026-11-09) — maquettes 1 à 6.
 *
 * L'application ouvre /app?source=android&v=<versionCode>. Le serveur vérifie la
 * maintenance et la version minimale, puis oriente : onboarding (une seule fois),
 * connexion / inscription, conditions à accepter, ou directement l'accueil si la session
 * est valide. Rien n'est stocké dans l'application : la session reste celle du site.
 */
class AppController extends Controller
{
    private const ONBOARDED = 'mx_onb';

    public function __construct(private LegalService $legal) {}

    /** 1. Écran de démarrage : contrôle du service et de la version, puis orientation. */
    public function start(Request $request)
    {
        $version = (int) $request->query('v', session('app_version', 0));
        if ($version > 0) {
            session(['app_version' => $version]);
        }
        if ($message = AndroidApp::maintenance()) {
            return response()->view('social.app.maintenance', ['message' => $message], 503)->header('Retry-After', '300');
        }
        if ($version > 0 && $version < AndroidApp::minVersion()) {
            return response()->view('social.app.update', ['store' => AndroidApp::storeUrl()], 426);
        }

        return view('social.app.splash', ['next' => $this->next($request)]);
    }

    /** 2. Onboarding : trois écrans, « Passer » à tout moment, montré une seule fois. */
    public function onboarding(Request $request)
    {
        if (auth('member')->check()) {
            return redirect()->route('social.home');
        }

        $step = max(1, min(3, (int) $request->query('etape', 1)));

        return view('social.app.onboarding', ['step' => $step]);
    }

    public function onboardingDone()
    {
        return redirect()->route('social.app.auth')->withCookie(cookie(self::ONBOARDED, '1', 60 * 24 * 365 * 2));
    }

    /** 3–4. Connexion / création de compte (formulaires branchés sur les vrais services). */
    public function auth(Request $request)
    {
        if (auth('member')->check()) {
            return redirect()->route('social.home');
        }

        return view('social.app.auth', [
            'tab' => $request->query('onglet') === 'inscription' ? 'register' : 'login',
            'documents' => $this->legal->current()->whereIn('type', ['terms', 'privacy'])->values(),
        ]);
    }

    /** 6. Première connexion réussie. */
    public function ready()
    {
        return view('social.app.ready', ['account' => auth('member')->user()]);
    }

    /** Maquette 14 — « Mon profil » : chiffres réels et accès aux espaces existants. */
    public function menu()
    {
        $me = auth('member')->user();

        return view('social.app.menu', [
            'me' => $me,
            'posts' => \App\Models\Social\Post::where('public_account_id', $me->id)->whereNull('mutual_id')->whereNull('group_id')->count(),
            'followers' => \App\Models\Social\UserFollow::where('followed_id', $me->id)->count(),
            'following' => \App\Models\Social\UserFollow::where('follower_id', $me->id)->count() + $me->follows()->count(),
            'legalPending' => $this->legal->pendingFor($me)->count(),
        ]);
    }

    /** État du service pour l'application (sans donnée personnelle). */
    public function status()
    {
        return response()->json([
            'ok' => AndroidApp::maintenance() === null,
            'maintenance' => AndroidApp::maintenance(),
            'min_version' => AndroidApp::minVersion(),
            'latest_version' => AndroidApp::latestVersion(),
            'store_url' => AndroidApp::storeUrl(),
            'policies' => $this->legal->current()->mapWithKeys(fn ($d) => [$d->type => $d->version]),
        ])->header('Cache-Control', 'no-store');
    }

    /** Liaison du domaine avec l'application (plein écran sans barre d'adresse). */
    public function assetLinks()
    {
        $prints = AndroidApp::fingerprints();
        $body = $prints ? [[
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => ['namespace' => 'android_app', 'package_name' => AndroidApp::package(), 'sha256_cert_fingerprints' => $prints],
        ]] : [];

        return response()->json($body, 200, [], JSON_UNESCAPED_SLASHES)->header('Cache-Control', 'public, max-age=3600');
    }

    private function next(Request $request): string
    {
        $account = auth('member')->user();
        if (! $account) {
            return $request->cookie(self::ONBOARDED) ? route('social.app.auth') : route('social.app.onboarding');
        }
        if ($this->legal->pendingFor($account)->isNotEmpty()) {
            return route('social.legal.pending', ['app' => 1]);
        }

        return route('social.home');
    }
}
