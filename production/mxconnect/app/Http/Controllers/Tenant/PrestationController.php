<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\PrestationRequest;
use App\Models\Central\Currency;
use App\Models\Tenant\CareClaim;
use App\Models\Tenant\Prestation;
use App\Services\ClaimCaptureService;
use Illuminate\Http\Request;

class PrestationController extends Controller
{
    public function __construct(private ClaimCaptureService $capture) {}

    /** MAKER — capture a prestation: verify rights, assess the split, screen for fraud. */
    public function store(PrestationRequest $request, CareClaim $claim)
    {
        $data = $request->validated();
        abort_unless($this->capture->versionFor($claim->member, $data['care_date']), 422, 'No applicable guarantee version');

        $result = $this->capture->capture($claim, [
            'act_type_id' => (int) $data['act_type_id'],
            'provider_id' => (int) $data['provider_id'],
            'care_date'   => $data['care_date'],
            'total_minor' => $this->currency()->toMinor((float) $data['total']),
            'emergency'   => $request->boolean('emergency'),
            'accident'    => $request->boolean('accident'),
        ], $request->user());
        if (! $result['ok']) {
            return back()->withInput()->with('rights_block', $result['reasons']);
        }

        $msg = __('mxconnect.prestation.captured');
        if ($result['alerts']) {
            $msg .= ' ' . __('mxconnect.prestation.alerts_raised', ['n' => count($result['alerts'])]);
        }

        return redirect()->route('claims.show', $claim)->with('success', $msg);
    }

    /** CHECKER — validate a captured prestation (separation of duties). */
    public function validatePrestation(Request $request, CareClaim $claim, Prestation $prestation)
    {
        \App\Support\TenantAccess::authorize();   // administrateur ou contrôleur-validateur
        abort_unless($prestation->care_claim_id === $claim->id, 404);
        abort_unless($prestation->status === 'captured', 422);
        if ($prestation->isCapturedBy($request->user())) {
            return back()->with('error', 'Vous avez saisi cette prestation : une autre personne doit la valider (contrôle « 4 yeux »).');
        }
        // Urgence soignée sans accord préalable (2026-11-19) : validation après la décision sur la régularisation.
        if ($prestation->authorization_missing && \App\Models\Tenant\Preauthorization::where('prestation_id', $prestation->id)->where('status', 'requested')->exists()) {
            return back()->with('error', 'Urgence soignée sans accord préalable : la demande de régularisation doit d’abord être examinée (Accords préalables).');
        }

        $prestation->update(['status' => 'validated'] + (Prestation::hasTraceColumns() ? ['validated_by' => $request->user()->id] : [])
            + (Prestation::hasDecisionColumns() ? ['decided_at' => now()] : []));
        // Adhérent relié à un compte MX-CONNECT (portail, 2026-11-20) : aucun détail médical dans la notification.
        \App\Support\MemberNotifier::notify((int) $claim->member_id, 'Une prise en charge a été validée',
            'Soins du ' . $prestation->care_date?->format('d/m/Y') . ' : détail dans votre espace.', 'prestations/' . $prestation->id);
        // Établissement de soins prévenu (phase 6, 2026-11-25) : sans donnée médicale.
        \App\Support\ProviderNotifier::notify((int) $prestation->provider_id, 'claim_validated', 'Prise en charge n° ' . $prestation->id . ' validée',
            'Adhérent ' . $claim->member?->member_code . ' · soins du ' . $prestation->care_date?->format('d/m/Y') . ' · part de la mutuelle : '
            . \App\Support\ProviderNotifier::money((int) $prestation->mutual_part_minor) . '. Elle peut être facturée.', 'claims', 'claims.view');
        return back()->with('success', __('mxconnect.prestation.validated'));
    }

    public function reject(Request $request, CareClaim $claim, Prestation $prestation)
    {
        \App\Support\TenantAccess::authorize();
        abort_unless($prestation->care_claim_id === $claim->id, 404);
        abort_unless($prestation->status === 'captured', 422);
        if ($prestation->isCapturedBy($request->user())) {
            return back()->with('error', 'Vous avez saisi cette prestation : une autre personne doit décider (contrôle « 4 yeux »).');
        }
        // Motif obligatoire (2026-11-15) : il est montré au prestataire dans « Mes prises en charge ».
        $data = $request->validate(['note' => ['required', 'string', 'min:5', 'max:500']], [
            'note.required' => 'Indiquez le motif du rejet : il sera communiqué au prestataire.',
            'note.min'      => 'Le motif du rejet est trop court.',
        ]);
        $prestation->update(['status' => 'rejected'] + (Prestation::hasTraceColumns() ? ['validated_by' => $request->user()->id] : [])
            + (Prestation::hasDecisionColumns() ? ['decision_note' => trim($data['note']), 'decided_at' => now()] : []));
        $this->capture->releaseException($prestation);
        \App\Support\MemberNotifier::notify((int) $claim->member_id, 'Une prise en charge a été rejetée',
            'Soins du ' . $prestation->care_date?->format('d/m/Y') . ' : motif dans votre espace.', 'prestations/' . $prestation->id);
        \App\Support\ProviderNotifier::notify((int) $prestation->provider_id, 'claim_rejected', 'Prise en charge n° ' . $prestation->id . ' rejetée',
            'Adhérent ' . $claim->member?->member_code . ' · soins du ' . $prestation->care_date?->format('d/m/Y') . ' · motif : ' . trim($data['note']), 'claims', 'claims.view');
        return back()->with('success', __('mxconnect.prestation.rejected'));
    }

    private function currency(): Currency
    {
        return Currency::on('central')->find(tenant('currency_id'))
            ?? Currency::on('central')->where('code', 'XAF')->firstOrFail();
    }
}
