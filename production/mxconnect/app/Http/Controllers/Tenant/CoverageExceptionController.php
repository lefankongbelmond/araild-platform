<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Central\Currency;
use App\Models\Tenant\ActType;
use App\Models\Tenant\CoverageException;
use App\Models\Tenant\Member;
use App\Models\Tenant\ServiceCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * DÉROGATIONS (mission « Mutuelle paramétrable », L2, 2026-11-18) : prise en charge exceptionnelle
 * pour UN bénéficiaire — dispense de carence, plafond supplémentaire, acte exclu accordé.
 * Demande motivée et justifiée ; décision par une AUTRE personne (« 4 yeux ») ; retrait possible.
 * Une dérogation ne modifie jamais la formule : le moteur l'applique en plus et la prestation
 * qui l'utilise en garde la trace. Rien n'est supprimé (historique conservé, journal d'audit).
 */
class CoverageExceptionController extends Controller
{
    public function index(Request $request)
    {
        $status = array_key_exists((string) $request->get('statut'), CoverageException::STATUSES) ? $request->get('statut') : null;
        $member = $request->filled('adherent') ? Member::where('member_code', trim((string) $request->get('adherent')))->first() : null;

        return view('tenant.exceptions.index', [
            'items' => CoverageException::with(['member', 'dependent', 'category', 'actType'])
                ->when($status, fn ($q) => $q->where('status', $status))
                ->when($member, fn ($q) => $q->where('member_id', $member->id))
                ->latest('id')->paginate(25)->withQueryString(),
            'counts' => CoverageException::selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status'),
            'status' => $status, 'member' => $member, 'currency' => $this->currency(),
            'categories' => ServiceCategory::where('active', true)->orderBy('sort')->get(),
            'acts' => ActType::orderBy('label')->get(['id', 'label']),
            'users' => \App\Models\Tenant\User::pluck('first_name', 'id'),
        ]);
    }

    public function store(Request $request)
    {
        $d = $request->validate([
            'member_code'         => ['required', 'string', 'max:40'],
            'dependent_id'        => ['nullable', 'integer'],
            'kind'                => ['required', Rule::in(array_keys(CoverageException::KINDS))],
            'service_category_id' => ['nullable', 'integer', Rule::exists('service_categories', 'id')],
            'act_type_id'         => ['nullable', 'integer', Rule::exists('act_types', 'id')],
            'amount'              => ['exclude_unless:kind,limit_extra', 'required', 'numeric', 'min:1', 'max:999999999'],
            'valid_from'          => ['required', 'date'],
            'valid_to'            => ['nullable', 'date', 'after_or_equal:valid_from'],
            'reason'              => ['required', 'string', 'min:5', 'max:160'],
            'justification'       => ['required', 'string', 'min:10', 'max:2000'],
        ], [
            'member_code.required' => 'Indiquez le numéro d’adhérent.',
            'amount.required' => 'Indiquez le montant du plafond supplémentaire.',
            'reason.required' => 'Indiquez le motif de la dérogation.',
            'justification.required' => 'Justifiez la dérogation (pièce, avis, contexte) : elle sera relue par une autre personne.',
            'justification.min' => 'La justification est trop courte.',
        ]);
        $member = Member::where('member_code', trim($d['member_code']))->first();
        if (! $member) {
            return back()->withInput()->withErrors(['member_code' => 'Aucun adhérent avec ce numéro.']);
        }
        if (! empty($d['dependent_id']) && ! $member->dependents()->whereKey($d['dependent_id'])->exists()) {
            return back()->withInput()->withErrors(['dependent_id' => 'Cet ayant droit n’appartient pas à l’adhérent.']);
        }
        if (empty($d['service_category_id']) && empty($d['act_type_id']) && $d['kind'] !== 'waiting_waiver') {
            return back()->withInput()->withErrors(['service_category_id' => 'Précisez la catégorie de soins ou l’acte concerné.']);
        }
        CoverageException::create([
            'member_id' => $member->id, 'dependent_id' => $d['dependent_id'] ?? null, 'kind' => $d['kind'],
            'service_category_id' => $d['service_category_id'] ?? null, 'act_type_id' => $d['act_type_id'] ?? null,
            'amount_minor' => $d['kind'] === 'limit_extra' ? $this->currency()->toMinor((float) $d['amount']) : null,
            'valid_from' => $d['valid_from'], 'valid_to' => $d['valid_to'] ?? null,
            'reason' => trim($d['reason']), 'justification' => trim($d['justification']),
            'status' => 'requested', 'requested_by' => $request->user()->id,
        ]);

        return redirect()->route('derogations.index', ['adherent' => $member->member_code])
            ->with('success', 'Dérogation demandée. Elle n’a aucun effet tant qu’une autre personne ne l’a pas accordée.');
    }

    public function approve(Request $request, CoverageException $exception)
    {
        return $this->decide($request, $exception, 'approved');
    }

    public function reject(Request $request, CoverageException $exception)
    {
        return $this->decide($request, $exception, 'rejected');
    }

    /** Retirer une dérogation accordée : effet pour les soins futurs ; les prestations déjà faites restent. */
    public function revoke(Request $request, CoverageException $exception)
    {
        abort_unless($exception->status === 'approved', 422);
        $d = $request->validate(['note' => ['required', 'string', 'min:5', 'max:500']], ['note.required' => 'Indiquez le motif du retrait.']);
        $exception->update(['status' => 'revoked', 'decision_note' => trim(($exception->decision_note ? $exception->decision_note . "\n" : '') . 'Retrait : ' . $d['note'])]);

        return back()->with('success', 'Dérogation retirée.');
    }

    private function decide(Request $request, CoverageException $exception, string $to)
    {
        abort_unless($exception->status === 'requested', 422);
        if ((int) $exception->requested_by === (int) $request->user()->id) {
            return back()->with('error', 'Vous avez demandé cette dérogation : une autre personne doit décider (contrôle « 4 yeux »).');
        }
        $d = $request->validate(['note' => [$to === 'rejected' ? 'required' : 'nullable', 'string', 'max:500']],
            ['note.required' => 'Indiquez le motif du refus.']);
        $exception->update(['status' => $to, 'decided_by' => $request->user()->id, 'decided_at' => now(), 'decision_note' => $d['note'] ?? null]);

        return back()->with('success', $to === 'approved' ? 'Dérogation accordée.' : 'Dérogation refusée.');
    }

    private function currency(): Currency
    {
        return Currency::on('central')->find(tenant('currency_id'))
            ?? Currency::on('central')->where('code', 'XAF')->firstOrFail();
    }
}
