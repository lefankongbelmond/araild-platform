<?php

namespace App\Http\Controllers\Social;

use App\Models\Central\Country;
use App\Models\Central\Mutual;
use App\Models\Social\MutualProfile;
use App\Models\Social\Opportunity;
use App\Models\Social\Post;
use App\Models\Social\Project;
use App\Support\SocialDomains;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * « Découvrir » (design Lot 2) : une seule recherche pour les organisations, les projets,
 * les événements à venir et les opportunités ouvertes, filtrable par domaine, pays et
 * région. Chiffres clés et régions les plus actives = comptages réels (démos exclues).
 */
class DiscoverController extends SocialController
{
    public const TABS = ['organisations' => 'Organisations', 'projets' => 'Projets', 'evenements' => 'Événements', 'opportunites' => 'Opportunités'];

    public function index(Request $request)
    {
        $tab = array_key_exists((string) $request->get('onglet'), self::TABS) ? $request->get('onglet') : 'organisations';
        $f = [
            'q'       => $request->string('q')->trim()->limit(100, '')->toString(),
            'domaine' => SocialDomains::valid($request->get('domaine')),
            'pays'    => (int) $request->get('pays') ?: null,
            'region'  => $request->string('region')->limit(120, '')->toString(),
            'type'    => $request->string('type')->limit(30, '')->toString(),
        ];
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $f['q']) . '%';
        $dom = $f['domaine'];
        // Domaine d'une organisation (fiche) ; un projet sans domaine hérite de ceux de sa page.
        $orgDomain = fn ($q) => $q->whereHas('profile', fn ($p) => SocialDomains::whereHasDomain($p, 'domains', $dom));
        $viaPage = fn ($q) => $q->whereHas('mutual.profile', fn ($p) => SocialDomains::whereHasDomain($p, 'domains', $dom));

        $items = match ($tab) {
            'projets' => Project::public()->with('mutual.profile')
                ->when($f['q'] !== '', fn ($w) => $w->where(fn ($x) => $x->where('title', 'like', $like)->orWhere('summary', 'like', $like)->orWhere('zone', 'like', $like)))
                ->when($dom, fn ($w) => $w->where(fn ($x) => SocialDomains::whereHasDomain($x, 'domains', $dom)->orWhere(fn ($y) => $viaPage($y->whereNull('domains')))))
                ->when($f['region'], fn ($w) => $w->where('region', $f['region']))
                ->when($f['pays'], fn ($w) => $w->whereHas('mutual', fn ($m) => $m->where('country_id', $f['pays'])))
                ->when(array_key_exists($f['type'], Project::PHASES), fn ($w) => $w->where('phase', $f['type']))
                ->orderByRaw("phase = 'completed'")->latest('published_at')->paginate(12),
            'evenements' => Post::publicFeed()->where('type', 'evenement')->where('event_starts_at', '>=', now()->startOfDay())->with('mutual.profile')
                ->when($f['q'] !== '', fn ($w) => $w->where(fn ($x) => $x->where('body', 'like', $like)->orWhere('event_location', 'like', $like)))
                ->when($dom, $viaPage)
                ->when($f['region'], fn ($w) => $w->whereHas('mutual', fn ($m) => $m->where('region', $f['region'])))
                ->when($f['pays'], fn ($w) => $w->whereHas('mutual', fn ($m) => $m->where('country_id', $f['pays'])))
                ->orderBy('event_starts_at')->paginate(12),
            'opportunites' => Opportunity::public()->open()->with(['mutual.profile', 'country'])
                ->when($f['q'] !== '', fn ($w) => $w->where(fn ($x) => $x->where('title', 'like', $like)->orWhere('description', 'like', $like)->orWhere('city', 'like', $like)))
                ->when($dom, $viaPage)
                ->when($f['region'], fn ($w) => $w->where('region', $f['region']))
                ->when($f['pays'], fn ($w) => $w->where('country_id', $f['pays']))
                ->when(array_key_exists($f['type'], Opportunity::CATEGORIES), fn ($w) => $w->where('category', $f['type']))
                ->latest('published_at')->paginate(12),
            default => $this->feed->mutualsQuery()->withCount(['projects' => fn ($p) => $p->where('status', 'published')])
                ->when($f['q'] !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', $like)->orWhere('city', 'like', $like)
                    ->orWhereHas('profile', fn ($p) => $p->where('tagline', 'like', $like)->orWhere('description', 'like', $like))))
                ->when($dom, $orgDomain)
                ->when($f['region'], fn ($w) => $w->where('region', $f['region']))
                ->when($f['pays'], fn ($w) => $w->where('country_id', $f['pays']))
                ->tap(fn ($w) => isset(MutualProfile::CATEGORIES[$f['type']]) ? MutualProfile::applyCategory($w, $f['type']) : $w)
                ->orderBy('name')->paginate(12),
        };
        $items->withQueryString();
        $state = $this->feed->viewerState($this->viewer(), []);

        return view('social.discover', [
            'tab' => $tab, 'f' => $f, 'items' => $items, 'followMap' => $state['follows'],
            'stats' => $this->stats(), 'regions' => $this->activeRegions(),
            'places' => $this->feed->places(),
            'countries' => Country::where('active', true)->whereIn('id', Mutual::where('status', '!=', 'suspended')->select('country_id'))->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Chiffres clés réels (contenus de démonstration exclus), mis en cache 5 min. */
    public static function stats(): array
    {
        return Cache::remember('social:discover:stats', 300, function () {
            $notDemo = fn ($q) => $q->whereDoesntHave('profile', fn ($p) => $p->where('is_demo', true));

            return [
                'organisations' => $notDemo(Mutual::where('status', '!=', 'suspended'))->count(),
                'projets'       => Project::public()->where('is_demo', false)->count(),
                'evenements'    => Post::publicFeed()->where('is_demo', false)->where('type', 'evenement')->where('event_starts_at', '>=', now()->startOfDay())->count(),
                'opportunites'  => Opportunity::public()->open()->where('is_demo', false)->count(),
                'membres'       => \App\Models\Central\PublicAccount::whereNull('suspended_at')->count(),
            ];
        });
    }

    /** Régions comptant le plus d'organisations visibles (5 premières). */
    private function activeRegions()
    {
        return Cache::remember('social:discover:regions', 300, fn () => Mutual::where('status', '!=', 'suspended')->whereNotNull('region')->where('region', '!=', '')
            ->selectRaw('region, count(*) as n')->groupBy('region')->orderByDesc('n')->limit(5)->pluck('n', 'region'));
    }
}
