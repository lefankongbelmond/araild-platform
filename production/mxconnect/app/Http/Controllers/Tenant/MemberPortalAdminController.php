<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Central\MemberLink;
use App\Models\Tenant\Dependent;
use App\Models\Tenant\Member;
use App\Models\Tenant\MemberChangeRequest;
use App\Services\Social\MemberPortalService;
use App\Support\MemberNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Portail adhérent, côté mutuelle (2026-11-20) : code d'activation remis à l'adhérent, retrait de
 * l'accès, et traitement des demandes de modification faites depuis le portail (appliquées seulement
 * à la validation ; un ayant droit ajouté n'est couvert qu'à partir de sa date d'intégration).
 */
class MemberPortalAdminController extends Controller
{
    public function __construct(private MemberPortalService $portal) {}

    public function code(Request $request, Member $member)
    {
        $c = $this->portal->issueCode((string) tenant('id'), $member->id, $request->user()->id);

        return back()->with('portal_code', ['code' => $c['code'], 'expires' => $c['expires_at']->format('d/m/Y')])
            ->with('success', 'Code d’activation créé : remettez-le à l’adhérent. Il ne sera plus affiché.');
    }

    public function revoke(Request $request, Member $member)
    {
        $n = $this->portal->revoke((string) tenant('id'), $member->id);

        return back()->with('success', $n ? 'Accès au portail retiré : le compte MX-CONNECT n’est plus relié à cette fiche.' : 'Aucun accès à retirer.');
    }

    public function index(Request $request)
    {
        $status = array_key_exists((string) $request->get('statut'), MemberChangeRequest::STATUSES) ? $request->get('statut') : 'requested';

        return view('tenant.member-requests.index', [
            'items' => MemberChangeRequest::with(['member', 'dependent'])->where('status', $status)->latest('id')->paginate(25)->withQueryString(),
            'counts' => MemberChangeRequest::selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status'),
            'status' => $status,
        ]);
    }

    public function decide(Request $request, MemberChangeRequest $memberRequest)
    {
        abort_unless($memberRequest->status === 'requested', 422);
        $d = $request->validate(['decision' => ['required', 'in:approved,rejected'], 'note' => ['nullable', 'string', 'max:500']]);
        if ($d['decision'] === 'rejected' && blank($d['note'] ?? null)) {
            return back()->with('error', 'Indiquez le motif du refus : il est communiqué à l’adhérent.');
        }
        DB::transaction(function () use ($memberRequest, $d, $request) {
            if ($d['decision'] === 'approved') {
                $this->apply($memberRequest);
            }
            $memberRequest->update(['status' => $d['decision'], 'decided_by' => $request->user()->id, 'decided_at' => now(), 'decision_note' => $d['note'] ?? null]);
        });
        $label = MemberChangeRequest::KINDS[$memberRequest->kind] ?? 'Demande';
        MemberNotifier::notify($memberRequest->member_id, $d['decision'] === 'approved' ? \App\Support\Msg::of(':kind : acceptée', ['kind' => ['__' => $label]]) : \App\Support\Msg::of(':kind : refusée', ['kind' => ['__' => $label]]),
            $d['decision'] === 'rejected' ? \App\Support\Msg::of('Motif : :note', ['note' => $d['note']]) : null, $memberRequest->kind === 'contact' ? 'profil' : 'beneficiaires');

        return back()->with('success', $d['decision'] === 'approved' ? 'Demande acceptée et appliquée.' : 'Demande refusée.');
    }

    private function apply(MemberChangeRequest $r): void
    {
        $p = $r->payload;
        $member = Member::findOrFail($r->member_id);
        $rel = fn ($v) => $v === 'adopted_child' ? 'child' : $v;
        match ($r->kind) {
            'contact' => $member->update(array_intersect_key($p, array_flip(['phone', 'address']))),
            'dependent_add' => $member->dependents()->create([
                'first_name' => $p['first_name'], 'last_name' => $p['last_name'], 'birth_date' => $p['birth_date'], 'sex' => $p['sex'],
                'relationship' => $rel($p['relationship']), 'status' => 'active',
            ] + (\Illuminate\Support\Facades\Schema::hasColumn('dependents', 'joined_on') ? ['joined_on' => now()->toDateString(), 'is_student' => (bool) ($p['is_student'] ?? false)] : [])),
            'dependent_update' => Dependent::where('member_id', $member->id)->whereKey($r->dependent_id)->firstOrFail()->update(array_filter([
                'first_name' => $p['first_name'] ?? null, 'last_name' => $p['last_name'] ?? null, 'birth_date' => $p['birth_date'] ?? null,
                'sex' => $p['sex'] ?? null, 'relationship' => isset($p['relationship']) ? $rel($p['relationship']) : null,
            ], fn ($v) => $v !== null) + (array_key_exists('is_student', $p) ? ['is_student' => (bool) $p['is_student']] : [])),
            'dependent_remove' => Dependent::where('member_id', $member->id)->whereKey($r->dependent_id)->firstOrFail()
                ->update(['status' => 'removed'] + (\Illuminate\Support\Facades\Schema::hasColumn('dependents', 'left_on') ? ['left_on' => now()->toDateString()] : [])),
        };
    }

    /** Relié à un compte MX-CONNECT ? (affiché sur la fiche de l'adhérent) */
    public static function linkFor(Member $member): ?MemberLink
    {
        return MemberLink::with('account')->where('mutual_id', (string) tenant('id'))->where('member_id', $member->id)->first();
    }
}
