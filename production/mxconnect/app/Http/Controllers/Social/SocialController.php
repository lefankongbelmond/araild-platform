<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\Central\PublicAccount;
use App\Services\Social\FeedService;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Http\Request;

/** Base commune des pages du réseau social (compte connecté + rendu des fils). */
abstract class SocialController extends Controller
{
    public function __construct(protected FeedService $feed) {}

    protected function viewer(): ?PublicAccount
    {
        return auth('member')->user();
    }

    /**
     * Rend une page contenant un fil. Avec ?fragment=1 (bouton « Charger plus »),
     * renvoie seulement les publications suivantes.
     */
    protected function feedView(Request $request, string $view, CursorPaginator $posts, array $data = [])
    {
        // Fils généraux : une publication sponsorisée au plus par page (après la 3e publication).
        $sponsored = null;
        if ($view === 'social.feed' && count($posts->items()) >= 3) {
            $sponsored = app(\App\Services\Social\SponsorService::class)->pick($this->viewer(), array_map(fn ($p) => $p->id, $posts->items()));
        }
        $state = $this->feed->viewerState($this->viewer(), array_merge($posts->items(), $sponsored ? [$sponsored->post] : []));
        if ($request->boolean('fragment')) {
            return response()->view('social.partials.feed', ['posts' => $posts, 'state' => $state, 'sponsored' => $sponsored])
                ->header('Cache-Control', 'no-store');
        }

        return view($view, $data + ['posts' => $posts, 'state' => $state, 'followMap' => $state['follows'], 'sponsored' => $sponsored]);
    }

    /** Filtres de fil autorisés depuis l'adresse. */
    protected function filters(Request $request): array
    {
        return array_filter([
            'type'     => $request->string('type')->limit(20, '')->toString(),
            'category' => $request->string('categorie')->limit(40, '')->toString(),
            'region'   => $request->string('region')->limit(120, '')->toString(),
            'city'     => $request->string('ville')->limit(120, '')->toString(),
            'q'        => $request->string('q')->trim()->limit(100, '')->toString(),
        ]);
    }
}
