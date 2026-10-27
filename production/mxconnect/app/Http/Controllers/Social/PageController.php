<?php

namespace App\Http\Controllers\Social;

use App\Models\Social\Post;

/** Pages publiques : accueil visiteur, à propos, confidentialité. */
class PageController extends SocialController
{
    public function landing()
    {
        if ($this->viewer()) {
            return redirect()->route('social.home');
        }

        // Accueil « Lot 2 ». Chiffres RÉELS de la base (aucune statistique inventée) ;
        // les contenus de démonstration sont exclus des compteurs.
        $stats = DiscoverController::stats();
        $domainCounts = \Illuminate\Support\Facades\Cache::remember('social:landing:domains', 300, fn () => collect(\App\Support\SocialDomains::ALL)
            ->map(fn ($l, $code) => \App\Support\SocialDomains::whereHasDomain(
                \App\Models\Social\MutualProfile::query()->whereHas('mutual', fn ($m) => $m->where('status', '!=', 'suspended')), 'domains', $code)->count())->all());

        return view('social.landing', [
            'stats'    => $stats,
            'domains'  => $domainCounts,
            'projects' => \App\Models\Social\Project::public()->with('mutual.profile')->orderByRaw("phase = 'completed'")->latest('published_at')->limit(4)->get(),
            'latest'   => Post::publicFeed()->with(\App\Services\Social\FeedService::WITH)->orderByDesc('published_at')->limit(3)->get(),
        ]);
    }

    public function about()
    {
        return view('social.about');
    }

    public function privacy()
    {
        // Version publiée la plus récente (documents versionnés, 2026-11-09) ; sinon la page historique.
        $doc = app(\App\Services\Social\LegalService::class)->currentOf('privacy');

        return $doc ? redirect()->route('social.legal.show', ['type' => 'privacy']) : view('social.privacy');
    }

    /** Centre d'aide (questions fréquentes) et formulaire de retour bêta (2026-11-09). */
    public function help()
    {
        return view('social.help');
    }

    public function feedback(\Illuminate\Http\Request $request)
    {
        $data = $request->validate([
            'category' => ['required', 'in:' . implode(',', array_keys(\App\Models\Social\BetaFeedback::CATEGORIES))],
            'message'  => ['required', 'string', 'min:10', 'max:3000'],
            'screen'   => ['nullable', 'string', 'max:120'],
            'contact'  => ['nullable', 'string', 'max:180'],
            'device'   => ['nullable', 'string', 'max:160'],
        ], ['message.min' => 'Décrivez votre retour en quelques mots (10 caractères minimum).']);
        \App\Models\Social\BetaFeedback::create($data + [
            'public_account_id' => auth('member')->id(),
            'app_version' => session('app_version') ? 'Android ' . session('app_version') : 'Web',
        ]);

        return back()->with('ok', 'Merci ! Votre retour a été transmis à l’équipe MX-CONNECT.');
    }

    /** Page PUBLIQUE de suppression de compte (exigée par Google Play pour l'application). */
    public function deletion()
    {
        return view('social.deletion');
    }
}
