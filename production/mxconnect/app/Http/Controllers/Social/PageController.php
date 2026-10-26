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
        return view('social.privacy');
    }
}
