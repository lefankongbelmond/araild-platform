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

    /**
     * Facture de tiers payant saisie par la mutuelle pour un prestataire (papier reçu au guichet).
     * Phase 5 (2026-11-26) : facture à lignes (les prestations validées de CE prestataire, pas encore
     * facturées), contrôlée ensuite par une autre personne, comme une facture envoyée en ligne.
     */
    public function createInvoice(Request $request, CareClaim $claim, \App\Services\Tenant\ProviderBillingService $billing)
    {
        abort_unless($request->user()->hasAnyRole(['benefits_manager', 'controller_validator', 'mutual_admin']), 403);
        $request->validate(['provider_id' => ['required', 'integer']]);
        $providerId = $request->integer('provider_id');

        if (\App\Services\Tenant\ProviderBillingService::ready()) {
            $ids = $billing->billable($providerId)->where('care_claim_id', $claim->id)->pluck('id')->all();
            if (! $ids) {
                return back()->with('error', 'Aucune prestation validée de ce prestataire à facturer dans ce recours (déjà facturée, ou aucune).');
            }
            try {
                $billing->submit($providerId, $ids, $request->user(), null, null, 'claim');
            } catch (\App\Services\AccountingRefusal $e) {
                return back()->with('error', $e->getMessage());
            }

            return back()->with('success', __('mxconnect.settlement.invoice_created'));
        }

        $amount = $claim->mutualTotalMinor();
        abort_if($amount <= 0, 422);
        $claim->invoices()->create([
            'provider_id'  => $providerId,
            'amount_minor' => $amount,
            'status'       => 'captured',
            'captured_by'  => $request->user()->id,
        ]);

        return back()->with('success', __('mxconnect.settlement.invoice_created'));
    }

    /**
     * Paiement d'une facture depuis le recours (ancienne route, conservée) : même règle que le
     * registre (phase 5) — facture validée, paiement effectué avec sa référence, « 4 yeux ».
     */
    public function payInvoice(Request $request, CareClaim $claim, ProviderInvoice $invoice, \App\Services\Tenant\ProviderBillingService $billing)
    {
        abort_unless($request->user()->hasRole('treasurer_accountant'), 403);
        abort_unless($invoice->care_claim_id === $claim->id, 404);
        abort_unless(in_array($invoice->status, ['captured', 'processed', 'prepared']), 422);

        if ($invoice->captured_by === $request->user()->id) {
            return back()->with('error', __('mxconnect.settlement.self_pay_blocked'));
        }
        $data = $request->validate([
            'mode' => ['nullable', 'in:' . implode(',', array_keys(\App\Services\Tenant\ProviderBillingService::MODES))],
            'payment_reference' => ['required', 'string', 'min:3', 'max:60'],
            'paid_on' => ['nullable', 'date'],
        ], ['payment_reference.required' => 'Indiquez la référence du paiement effectué (n° de virement, de transaction ou de reçu).']);
        try {
            $billing->pay($invoice, $request->user(), $data['mode'] ?? 'bank', $data['payment_reference'], $data['paid_on'] ?? now()->toDateString());
        } catch (\App\Services\AccountingRefusal $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('mxconnect.settlement.invoice_paid'));
    }

    /** Facture PDF jointe par le prestataire (réservée aux rôles qui voient le détail médical). */
    public function invoiceDocument(CareClaim $claim, ProviderInvoice $invoice, \App\Services\Tenant\InvoiceDocumentService $docs)
    {
        abort_unless($invoice->care_claim_id === $claim->id, 404);
        \App\Support\HealthAccessLog::record('invoice_document', $invoice->id);

        return $docs->download($invoice);
    }
}
