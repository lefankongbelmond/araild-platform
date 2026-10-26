<?php

namespace App\Http\Controllers\Social;

use App\Models\Social\Opportunity;
use App\Models\Social\SocialRequest;
use App\Services\Social\PageService;
use Illuminate\Http\Request;

/**
 * Emplois et opportunités publiés par les pages du réseau (mutuelles et pages
 * des membres) : recherche publique et fiche détaillée. Les offres dont la date
 * limite est passée ne sont plus proposées par défaut.
 */
class OpportunityController extends SocialController
{
    public function index(Request $request)
    {
        $q = $request->string('q')->trim()->limit(100, '')->toString();
        $category = (string) $request->get('categorie');
        $category = $category === 'emplois' || array_key_exists($category, Opportunity::CATEGORIES) ? $category : null;
        $contract = array_key_exists((string) $request->get('contrat'), Opportunity::CONTRACTS) ? $request->get('contrat') : null;
        $workplace = array_key_exists((string) $request->get('lieu'), Opportunity::WORKPLACES) ? $request->get('lieu') : null;
        $region = $request->string('region')->limit(120, '')->toString();
        $all = $request->boolean('toutes');
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';

        $items = Opportunity::public()->with(['mutual.profile', 'country'])
            ->when(! $all, fn ($w) => $w->open())
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('title', 'like', $like)->orWhere('description', 'like', $like)
                ->orWhere('city', 'like', $like)->orWhereHas('mutual', fn ($m) => $m->where('name', 'like', $like))))
            ->when($category === 'emplois', fn ($w) => $w->whereIn('category', Opportunity::JOB_CATEGORIES))
            ->when($category && $category !== 'emplois', fn ($w) => $w->where('category', $category))
            ->when($contract, fn ($w) => $w->where('contract_type', $contract))
            ->when($workplace, fn ($w) => $w->where('workplace', $workplace))
            ->when($region, fn ($w) => $w->where('region', $region))
            ->orderByDesc('published_at')->orderByDesc('id')
            ->paginate(15)->withQueryString();

        return view('social.opportunities.index', [
            'items' => $items, 'q' => $q, 'category' => $category, 'contract' => $contract, 'workplace' => $workplace,
            'region' => $region, 'all' => $all,
            'regions' => Opportunity::public()->whereNotNull('region')->distinct()->orderBy('region')->pluck('region'),
        ]);
    }

    public function show(Opportunity $opportunity)
    {
        $viewer = $this->viewer();
        $opportunity->load(['mutual.profile', 'country', 'project']);
        $canManage = PageService::canPublish($viewer, $opportunity->mutual);
        abort_unless($opportunity->isPublic() || $canManage, 404);

        $mine = $viewer ? SocialRequest::where('public_account_id', $viewer->id)->where('opportunity_id', $opportunity->id)
            ->latest('id')->first() : null;

        return view('social.opportunities.show', [
            'o' => $opportunity, 'page' => $opportunity->mutual, 'canManage' => $canManage, 'mine' => $mine,
            'isDemo' => $opportunity->is_demo || $opportunity->mutual->profile?->is_demo,
            'similar' => Opportunity::public()->open()->where('id', '!=', $opportunity->id)->where('category', $opportunity->category)
                ->with('mutual')->latest('published_at')->limit(4)->get(),
        ]);
    }
}
