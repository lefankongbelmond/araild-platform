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

        $prestation->update(['status' => 'validated'] + (Prestation::hasTraceColumns() ? ['validated_by' => $request->user()->id] : []));
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
        $prestation->update(['status' => 'rejected'] + (Prestation::hasTraceColumns() ? ['validated_by' => $request->user()->id] : []));
        return back()->with('success', __('mxconnect.prestation.rejected'));
    }

    private function currency(): Currency
    {
        return Currency::on('central')->find(tenant('currency_id'))
            ?? Currency::on('central')->where('code', 'XAF')->firstOrFail();
    }
}
