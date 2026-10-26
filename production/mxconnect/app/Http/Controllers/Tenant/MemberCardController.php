<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Concerns\MutualContext;
use App\Models\Tenant\Antenna;
use App\Models\Tenant\Member;
use App\Services\DigitalCardService;
use Illuminate\Http\Request;

/**
 * Cartes de membre : suivi (droits ouverts, carte imprimée), impression
 * individuelle ou par lot (planche A4). Le QR code porte un jeton signé,
 * jamais de donnée médicale.
 */
class MemberCardController extends Controller
{
    use MutualContext;

    public const ROLES = ['mutual_admin', 'enrollment_agent', 'controller_validator'];

    public function index(Request $request)
    {
        $this->requireRoles($request, self::ROLES);
        $q = trim((string) $request->get('q'));
        $state = (string) $request->get('etat');
        $antenna = (int) $request->get('antenne');
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';

        $members = Member::query()
            ->with(['subscriptions' => fn ($s) => $s->where('status', 'validated')->latest('effective_date')->with('guaranteeVersion.guarantee')])
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('last_name', 'like', $like)->orWhere('first_name', 'like', $like)->orWhere('member_code', 'like', $like)))
            ->when($antenna, fn ($w) => $w->where('antenna_id', $antenna))
            ->when($state === 'a-imprimer', fn ($w) => $w->where('status', 'active')->whereNull('card_printed_at')->whereHas('subscriptions', fn ($s) => $s->where('status', 'validated')))
            ->when($state === 'imprimees', fn ($w) => $w->whereNotNull('card_printed_at'))
            ->when($state === 'sans-droits', fn ($w) => $w->where(fn ($x) => $x->where('status', '!=', 'active')->orWhereDoesntHave('subscriptions', fn ($s) => $s->where('status', 'validated'))))
            ->orderBy('last_name')->paginate(20)->withQueryString();

        $eligible = Member::where('status', 'active')->whereHas('subscriptions', fn ($s) => $s->where('status', 'validated'));

        return view('tenant.cards.index', [
            'members' => $members, 'antennas' => Antenna::orderBy('name')->get(),
            'filters' => compact('q', 'state', 'antenna'),
            'stats' => [
                'eligible' => (clone $eligible)->count(),
                'to_print' => (clone $eligible)->whereNull('card_printed_at')->count(),
                'printed'  => Member::whereNotNull('card_printed_at')->count(),
                'blocked'  => Member::where('status', '!=', 'active')->count(),
            ],
        ]);
    }

    /** Planche d'impression (jusqu'à 10 cartes par page A4). */
    public function batch(Request $request, DigitalCardService $cards)
    {
        $this->requireRoles($request, self::ROLES);
        $ids = array_slice(array_map('intval', (array) $request->input('ids', [])), 0, 100);
        $members = Member::whereIn('id', $ids)->where('status', 'active')
            ->with(['subscriptions' => fn ($s) => $s->where('status', 'validated')->latest('effective_date')->with('guaranteeVersion.guarantee')])
            ->orderBy('last_name')->get()->filter(fn ($m) => $m->subscriptions->isNotEmpty())->values();
        abort_if($members->isEmpty(), 422, 'Aucune carte imprimable dans la sélection (adhérent actif avec souscription validée).');

        return view('tenant.cards.batch', [
            'members' => $members,
            'tokens' => $members->mapWithKeys(fn ($m) => [$m->id => $cards->issueToken($m)]),
        ]);
    }

    public function markPrinted(Request $request)
    {
        $this->requireRoles($request, self::ROLES);
        $ids = array_map('intval', (array) $request->input('ids', []));
        $n = Member::whereIn('id', $ids)->update(['card_printed_at' => now()]);

        return redirect()->route('cards.index')->with('success', $n . ' carte(s) marquée(s) comme imprimée(s).');
    }
}
