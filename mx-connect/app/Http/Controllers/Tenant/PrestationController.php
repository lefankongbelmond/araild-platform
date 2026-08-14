<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\PrestationRequest;
use App\Models\Central\Currency;
use App\Models\Tenant\CareClaim;
use App\Models\Tenant\GuaranteeVersion;
use App\Models\Tenant\Prestation;
use App\Services\ClaimAssessmentService;
use App\Services\FraudDetectionService;
use App\Services\RightsVerificationService;
use Illuminate\Http\Request;

class PrestationController extends Controller
{
    public function __construct(
        private ClaimAssessmentService $assessment,
        private FraudDetectionService $fraud,
        private RightsVerificationService $rights,
    ) {}

    /** MAKER — capture a prestation: verify rights, assess the split, screen for fraud. */
    public function store(PrestationRequest $request, CareClaim $claim)
    {
        $data = $request->validated();
        $member = $claim->member;

        // Applicable guarantee version on the care date (from the member's validated subscription).
        $subscription = $member->subscriptions()->where('status', 'validated')->latest('effective_date')->first();
        abort_unless($subscription, 422, 'No active subscription');
        $version = GuaranteeVersion::applicableOn($subscription->guaranteeVersion->guarantee_id, $data['care_date']);
        abort_unless($version, 422, 'No applicable guarantee version');

        // Rights check — a failing check blocks capture (with reasons).
        $verdict = $this->rights->verify($member, $version->guarantee_id, $data['care_date'], $data['act_type_id'], $data['provider_id']);
        if (! $verdict['ok']) {
            return back()->withInput()->with('rights_block', $verdict['reasons']);
        }

        $currency = $this->currency();
        $totalMinor = $currency->toMinor((float) $data['total']);

        // Assess the mutual/beneficiary split (ceiling-capped).
        $split = $this->assessment->assess($version, $data['act_type_id'], $totalMinor, $member->id, $data['care_date']);

        $prestation = $claim->prestations()->create([
            'act_type_id'            => $data['act_type_id'],
            'provider_id'            => $data['provider_id'],
            'guarantee_version_id'   => $version->id,
            'care_date'              => $data['care_date'],
            'total_minor'            => $totalMinor,
            'mutual_part_minor'      => $split['mutual_minor'],
            'beneficiary_part_minor' => $split['beneficiary_minor'],
            'status'                 => 'captured',
        ]);

        // A guarantee version used by a claim becomes immutable.
        $version->update(['locked' => true]);

        // Anti-fraud screening (non-blocking) — writes alerts.
        $alerts = $this->fraud->screen($prestation, $member->id, $split['capped']);

        $msg = __('mxconnect.prestation.captured');
        if ($alerts) {
            $msg .= ' ' . __('mxconnect.prestation.alerts_raised', ['n' => count($alerts)]);
        }

        return redirect()->route('claims.show', $claim)->with('success', $msg);
    }

    /** CHECKER — validate a captured prestation (separation of duties). */
    public function validatePrestation(Request $request, CareClaim $claim, Prestation $prestation)
    {
        abort_unless($request->user()->can('subscription.validate') || $request->user()->hasAnyRole(['controller_validator', 'mutual_admin']), 403);
        abort_unless($prestation->status === 'captured', 422);
        abort_unless($prestation->care_claim_id === $claim->id, 404);

        $prestation->update(['status' => 'validated']);
        return back()->with('success', __('mxconnect.prestation.validated'));
    }

    public function reject(Request $request, CareClaim $claim, Prestation $prestation)
    {
        abort_unless($request->user()->hasAnyRole(['controller_validator', 'mutual_admin']), 403);
        abort_unless($prestation->care_claim_id === $claim->id, 404);
        $prestation->update(['status' => 'rejected']);
        return back()->with('success', __('mxconnect.prestation.rejected'));
    }

    private function currency(): Currency
    {
        return Currency::on('central')->find(tenant('currency_id'))
            ?? Currency::on('central')->where('code', 'XAF')->firstOrFail();
    }
}
