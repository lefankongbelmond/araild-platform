<?php

namespace App\Http\Controllers\Social;

use App\Enums\OrganizationType;
use App\Enums\PostType;
use App\Http\Controllers\Controller;
use App\Models\Central\Country;
use App\Models\Central\Organization;
use App\Models\Central\Post;
use App\Models\Central\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Module 3 — "Découvrir": real keyword search + filters over published
 * organizations, their projects, events and opportunities.
 *
 * No map: organizations only carry a free-text region/city, not coordinates, so a
 * map would have to guess positions. Regions are instead aggregated from real data.
 */
class DiscoverController extends Controller
{
    public const SECTIONS = ['organisations', 'projets', 'evenements', 'opportunites'];

    public function index(Request $request)
    {
        $viewer = $request->user('member');
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'section' => ['nullable', 'in:'.implode(',', self::SECTIONS)],
            'type' => ['nullable', 'in:'.implode(',', array_column(OrganizationType::cases(), 'value'))],
            'domaine' => ['nullable', 'in:'.implode(',', config('mxconnect.social.domains'))],
            'pays' => ['nullable', 'integer'],
            'region' => ['nullable', 'string', 'max:120'],
        ]);
        $section = $filters['section'] ?? 'organisations';
        $q = trim($filters['q'] ?? '');

        // Filters on the organization apply to every section (projects/posts inherit them).
        $orgFilter = function (Builder $o) use ($filters) {
            $o->published()
                ->when($filters['type'] ?? null, fn ($o, $v) => $o->where('type', $v))
                ->when($filters['domaine'] ?? null, fn ($o, $v) => $o->inDomain($v))
                ->when($filters['pays'] ?? null, fn ($o, $v) => $o->where('country_id', $v))
                ->when($filters['region'] ?? null, fn ($o, $v) => $o->where('region', $v));
        };
        $like = '%'.str_replace(['%', '_'], ' ', $q).'%';

        $results = match ($section) {
            'projets' => Project::query()->whereHas('organization', $orgFilter)->with('organization')
                ->when($q !== '', fn ($p) => $p->where(fn ($p) => $p->where('title', 'like', $like)
                    ->orWhere('summary', 'like', $like)->orWhere('zone', 'like', $like)))
                ->latest(),
            'evenements', 'opportunites' => Post::visibleTo($viewer)->whereHas('organization', $orgFilter)
                ->with('organization')
                ->where('type', $section === 'evenements' ? PostType::Event->value : PostType::Opportunity->value)
                ->when($section === 'evenements', fn ($p) => $p->where('event_starts_at', '>=', now())->orderBy('event_starts_at'))
                ->when($section === 'opportunites', fn ($p) => $p->latest('published_at'))
                ->when($q !== '', fn ($p) => $p->where('body', 'like', $like)),
            default => Organization::query()->tap($orgFilter)
                ->when($q !== '', fn ($o) => $o->where(fn ($o) => $o->where('name', 'like', $like)
                    ->orWhere('tagline', 'like', $like)->orWhere('description', 'like', $like)
                    ->orWhere('city', 'like', $like)))
                ->orderByDesc('followers_count')->orderBy('name'),
        };

        return view('social.discover', [
            'viewer' => $viewer,
            'section' => $section,
            'filters' => $filters,
            'results' => $results->paginate(12)->withQueryString(),
            'countries' => Country::where('active', true)->orderBy('name')->get(['id', 'name']),
            'regions' => Organization::published()->whereNotNull('region')
                ->select('region', DB::raw('count(*) as total'))
                ->groupBy('region')->orderByDesc('total')->limit(8)->get(),
            'totals' => [
                'organisations' => Organization::published()->count(),
                'projets' => Project::ofPublishedOrganizations()->count(),
            ],
        ]);
    }
}
