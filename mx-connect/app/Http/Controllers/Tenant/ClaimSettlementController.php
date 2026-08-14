<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\CareClaim;
use App\Models\Tenant\ProviderInvoice;
use App\Models\Tenant\Reimbursement;
use App\Models\Tenant\TreasuryMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Settles a care claim once its prestations are validated, via either path:
 *   - reimbursement  : the mutual pays the MEMBER the covered part;
 *   - tiers payant   : the mutual pays the PROVIDER directly.
 * Paying records a treasury OUTFLOW (mirrors the Module-8 inflow on collections).
 */
class ClaimSettlementController extends Controller
{
    /** Create a reimbursement request for the claim's validated mutual liability. */
    public function requestReimbursement(Request $request, CareClaim $claim)
    {
        abort_unless($request->user()->hasAnyRole(['controller_validator', 'mutual_admin', 'treasurer_accountant']), 403);
        abort_if($claim->reimbursement()->exists(), 422);

        $amount = $claim->mutualTotalMinor();
        abort_if($amount <= 0, 422);

        $claim->reimbursement()->create([
            'requested_minor' => $amount,
            'granted_minor'   => $amount,
            'status'          => 'requested',
            'captured_by'     => $request->user()->id,
        ]);

        return back()->with('success', __('mxconnect.settlement.reimbursement_requested'));
    }

    /** Pay a reimbursement to the member (maker-checker: payer ≠ requester). */
    public function payReimbursement(Request $request, CareClaim $claim, Reimbursement $reimbursement)
    {
        abort_unless($request->user()->hasRole('treasurer_accountant'), 403);
        abort_unless($reimbursement->care_claim_id === $claim->id, 404);
        abort_unless(in_array($reimbursement->status, ['requested', 'processed', 'prepared']), 422);

        // Separation of duties: whoever requested it cannot also pay it.
        if ($reimbursement->captured_by === $request->user()->id) {
            return back()->with('error', __('mxconnect.settlement.self_pay_blocked'));
        }

        DB::transaction(function () use ($request, $reimbursement) {
            $reimbursement->update(['status' => 'paid', 'validated_by' => $request->user()->id]);

            TreasuryMovement::create([
                'direction'        => 'outflow',
                'source'           => 'reimbursement',
                'amount_minor'     => $reimbursement->granted_minor,
                'mode'             => $request->get('mode', 'bank'),
                'linked_reference' => 'RMB-' . $reimbursement->id,
                'moved_on'         => now()->toDateString(),
            ]);
        });

        return back()->with('success', __('mxconnect.settlement.reimbursement_paid'));
    }

    /** Register a tiers-payant provider invoice for the claim. */
    public function createInvoice(Request $request, CareClaim $claim)
    {
        abort_unless($request->user()->hasAnyRole(['benefits_manager', 'controller_validator', 'mutual_admin']), 403);
        $request->validate(['provider_id' => ['required', 'integer']]);

        $amount = $claim->mutualTotalMinor();
        abort_if($amount <= 0, 422);

        $claim->invoices()->create([
            'provider_id'  => $request->integer('provider_id'),
            'amount_minor' => $amount,
            'status'       => 'captured',
            'captured_by'  => $request->user()->id,
        ]);

        return back()->with('success', __('mxconnect.settlement.invoice_created'));
    }

    /** Pay a provider invoice (maker-checker: payer ≠ capturer). */
    public function payInvoice(Request $request, CareClaim $claim, ProviderInvoice $invoice)
    {
        abort_unless($request->user()->hasRole('treasurer_accountant'), 403);
        abort_unless($invoice->care_claim_id === $claim->id, 404);
        abort_unless(in_array($invoice->status, ['captured', 'processed', 'prepared']), 422);

        if ($invoice->captured_by === $request->user()->id) {
            return back()->with('error', __('mxconnect.settlement.self_pay_blocked'));
        }

        DB::transaction(function () use ($request, $invoice) {
            $invoice->update(['status' => 'paid', 'validated_by' => $request->user()->id]);

            TreasuryMovement::create([
                'direction'        => 'outflow',
                'source'           => 'provider_invoice',
                'amount_minor'     => $invoice->amount_minor,
                'mode'             => $request->get('mode', 'bank'),
                'linked_reference' => 'INV-' . $invoice->id,
                'moved_on'         => now()->toDateString(),
            ]);
        });

        return back()->with('success', __('mxconnect.settlement.invoice_paid'));
    }
}
