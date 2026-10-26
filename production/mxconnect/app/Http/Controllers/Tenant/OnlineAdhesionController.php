<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Social\AdhesionController;
use App\Models\Central\MemberLink;
use App\Models\Central\Mutual;
use App\Models\Social\AdhesionDocument;
use App\Models\Social\AdhesionRequest;
use App\Models\Tenant\Member;
use App\Services\Social\AdhesionService;
use App\Services\Social\NotificationService;
use Illuminate\Http\Request;

/**
 * Instruction des demandes d'adhésion reçues en ligne. Données personnelles :
 * réservé aux rôles administrateur, agent d'adhésion et contrôleur-validateur
 * (le community manager n'y a PAS accès). Une mutuelle ne voit que ses dossiers.
 */
class OnlineAdhesionController extends Controller
{
    public const ROLES = ['mutual_admin', 'enrollment_agent', 'controller_validator'];

    public function __construct(private AdhesionService $adhesions, private NotificationService $notifications) {}

    public function index(Request $request)
    {
        $mutual = $this->authorizeMutual($request);
        $status = array_key_exists((string) $request->get('statut'), AdhesionRequest::STATUSES) ? $request->get('statut') : null;

        return view('tenant.adhesions.index', [
            'items'  => AdhesionRequest::where('mutual_id', $mutual->id)
                ->when($status, fn ($q) => $q->where('status', $status), fn ($q) => $q->whereIn('status', AdhesionRequest::OPEN))
                ->withCount('documents')->latest()->paginate(25)->withQueryString(),
            'counts' => AdhesionRequest::where('mutual_id', $mutual->id)->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status'),
            'status' => $status,
        ]);
    }

    public function show(Request $request, string $reference)
    {
        $mutual = $this->authorizeMutual($request);
        $r = $this->find($mutual, $reference)->load(['documents', 'events', 'conversation', 'account']);

        return view('tenant.adhesions.show', ['r' => $r, 'transitions' => AdhesionService::TRANSITIONS[$r->status] ?? []]);
    }

    public function decide(Request $request, string $reference)
    {
        $mutual = $this->authorizeMutual($request);
        $r = $this->find($mutual, $reference);
        $data = $request->validate([
            'status' => ['required', 'in:in_review,info_requested,accepted,rejected'],
            'note'   => ['nullable', 'string', 'max:2000'],
        ]);
        // Décision finale : administrateur ou contrôleur-validateur uniquement.
        if (in_array($data['status'], ['accepted', 'rejected'], true)) {
            abort_unless($request->user()->hasAnyRole(['mutual_admin', 'controller_validator']), 403,
                'La décision finale revient à l’administrateur ou au contrôleur-validateur.');
        }
        $this->adhesions->decide($r, $data['status'], $data['note'] ?? null, $request->user()->id, $request->user()->name);

        return back()->with('success', 'Dossier mis à jour : ' . AdhesionRequest::STATUSES[$data['status']] . '.');
    }

    public function document(Request $request, string $reference, int $document)
    {
        $mutual = $this->authorizeMutual($request);
        $r = $this->find($mutual, $reference);

        return AdhesionController::download(AdhesionDocument::where('adhesion_request_id', $r->id)->findOrFail($document));
    }

    /** Ouvre la fiche « nouvel adhérent » préremplie avec le dossier (l'agent vérifie puis enregistre). */
    public function prefill(Request $request, string $reference)
    {
        $mutual = $this->authorizeMutual($request);
        $r = $this->find($mutual, $reference);
        abort_unless($r->status === 'accepted', 422, 'Acceptez d’abord la demande.');
        abort_unless($request->user()->hasAnyRole(['enrollment_agent', 'mutual_admin']), 403);

        return redirect()->route('members.create')->withInput([
            'first_name' => $r->first_name, 'last_name' => $r->last_name,
            'birth_date' => $r->birth_date->toDateString(), 'sex' => $r->sex,
            'phone' => $r->phone, 'id_document' => $r->id_document_number,
            'address' => trim(collect([$r->address, $r->city])->filter()->implode(', ')),
        ])->with('success', "Fiche préremplie depuis le dossier {$r->reference}. Vérifiez puis enregistrez, puis reliez le compte depuis le dossier.");
    }

    /** Relie le compte du demandeur à une fiche adhérent existante (accès à l'espace adhérent). */
    public function link(Request $request, string $reference)
    {
        $mutual = $this->authorizeMutual($request);
        $r = $this->find($mutual, $reference);
        abort_unless($r->status === 'accepted', 422, 'Acceptez d’abord la demande.');
        abort_unless($request->user()->hasAnyRole(['enrollment_agent', 'mutual_admin']), 403);
        $data = $request->validate(['member_code' => ['required', 'string', 'max:40']]);

        $member = Member::where('member_code', trim($data['member_code']))->first();
        if (! $member) {
            return back()->withErrors(['member_code' => 'Aucun adhérent avec ce code dans votre mutuelle.']);
        }
        $existing = MemberLink::where('public_account_id', $r->public_account_id)->where('mutual_id', $mutual->id)->first();
        if ($existing && (int) $existing->member_id !== (int) $member->id) {
            return back()->withErrors(['member_code' => 'Ce compte est déjà relié à une autre fiche (' . $existing->member_code . ').']);
        }
        MemberLink::updateOrCreate(
            ['public_account_id' => $r->public_account_id, 'mutual_id' => $mutual->id],
            ['member_id' => $member->id, 'member_code' => $member->member_code],
        );
        $r->update(['member_id' => $member->id, 'member_code' => $member->member_code]);
        $this->notifications->notify($r->public_account_id, 'adhesion',
            "Votre compte est relié à votre fiche adhérent chez {$mutual->name}", 'Numéro d’adhérent : ' . $member->member_code,
            '/compte/adhesions/' . $r->reference);

        return back()->with('success', "Compte relié à la fiche {$member->member_code}.");
    }

    private function find(Mutual $mutual, string $reference): AdhesionRequest
    {
        return AdhesionRequest::where('mutual_id', $mutual->id)->where('reference', $reference)->firstOrFail();
    }

    private function authorizeMutual(Request $request): Mutual
    {
        abort_unless($request->user()?->hasAnyRole(self::ROLES), 403);

        return tenant();
    }
}
