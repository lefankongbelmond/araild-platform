<?php

namespace App\Http\Controllers\Social;

use App\Models\Central\Mutual;
use App\Models\Social\Post;

/** Pages publiques : accueil visiteur, à propos, confidentialité. */
class PageController extends SocialController
{
    public function landing()
    {
        if ($this->viewer()) {
            return redirect()->route('social.home');
        }

        // Chiffres RÉELS de la base (aucune statistique inventée) ; les contenus de
        // démonstration sont exclus pour ne pas gonfler les compteurs.
        $stats = [
            'mutuals' => Mutual::operational()->where('status', 'approved')->count(),
            'posts'   => Post::publicFeed()->where('is_demo', false)->count(),
        ];
        $mutuals = $this->feed->mutualsQuery()->orderByDesc('status')->orderBy('name')->limit(6)->get();
        $latest = Post::publicFeed()->with(\App\Services\Social\FeedService::WITH)->orderByDesc('published_at')->limit(3)->get();

        return view('social.landing', [
            'stats' => $stats, 'mutuals' => $mutuals, 'latest' => $latest,
            'state' => $this->feed->viewerState(null, []),
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
