<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Concerns\MutualContext;
use App\Models\Central\CareProvider;
use App\Models\Tenant\ProviderInvoice;
use App\Models\Tenant\ProviderInvoiceLine;
use App\Services\AccountingRefusal;
use App\Services\Tenant\ProviderBillingService;
use App\Support\TenantAccess;
use Illuminate\Http\Request;

/**
 * Registre des factures des prestataires (tiers payant, 2026-11-03) : toutes les factures
 * en un seul écran, au lieu d'ouvrir chaque recours. Le trésorier paie (hors plateforme :
 * MX-CONNECT enregistre le paiement, n'encaisse ni ne verse rien) ; l'administrateur ou le
 * contrôleur peut rejeter une facture, avec un motif visible du prestataire.
 */
class InvoiceRegisterController extends Controller
{
    use MutualContext;

    private const TABS = ['a-controler' => 'À contrôler', 'a-payer' => 'Validées, à payer', 'payees' => 'Payées', 'rejetees' => 'Rejetées'];

    public function index(Request $request)
    {
        $statut = $this->tab($request);
        $q = $this->query($request, $statut)->with('careClaim.member');
        if (ProviderBillingService::ready()) {
            $q->withCount(['lines', 'lines as pending_lines_count' => fn ($l) => $l->where('status', 'pending'), 'lines as rejected_lines_count' => fn ($l) => $l->where('status', 'rejected')]);
        }
        $invoices = $q->latest('id')->paginate(30)->withQueryString();
        $providerIds = ProviderInvoice::distinct()->pluck('provider_id');
        $totals = [];
        foreach (array_keys(self::TABS) as $k) {
            $base = $this->scope(ProviderInvoice::query(), $k);
            $totals[$k] = [(clone $base)->count(), (int) (clone $base)->sum('amount_minor')];
        }

        return view('tenant.invoices.index', [
            'invoices'  => $invoices,
            'statut'    => $statut,
            'tabs'      => self::TABS,
            'currency'  => $this->currency(),
            'providers' => CareProvider::on('central')->whereIn('id', $providerIds)->orderBy('name')->pluck('name', 'id'),
            'totals'    => $totals,
            'modes'     => ProviderBillingService::MODES,
            'canPay'    => TenantAccess::can('invoices.pay'),
            'canReject' => TenantAccess::can('invoices.reject'),
            'canDecide' => TenantAccess::can('invoices.lines.decide'),
            'canDoc'    => TenantAccess::can('invoices.document'),
            'canClaim'  => TenantAccess::can('claims.show'),
        ]);
    }

    /** Détail d'une facture : lignes à contrôler, historique, paiement. */
    public function show(ProviderInvoice $invoice, ProviderBillingService $billing)
    {
        $health = TenantAccess::can('capacite.donnees-sante');
        $ready = ProviderBillingService::ready();
        $lines = $ready ? $invoice->lines()->with(array_merge(['prestation.careClaim.member', 'prestation.careClaim.dependent'], $health ? ['prestation.actType'] : []))
            ->orderBy('care_date')->orderBy('id')->get() : collect();
        $staff = \App\Models\Tenant\User::whereIn('id', collect([$invoice->captured_by, $invoice->checked_by, $invoice->validated_by])
            ->merge($ready ? $invoice->events()->pluck('user_id') : [])->merge($lines->pluck('decided_by'))->filter()->unique())->get()->mapWithKeys(fn ($u) => [$u->id => $u->name]);

        return view('tenant.invoices.show', [
            'inv'       => $invoice->load('careClaim.member'),
            'provider'  => CareProvider::on('central')->find($invoice->provider_id),
            'lines'     => $lines,
            'events'    => $ready ? $invoice->events()->get() : collect(),
            'staff'     => $staff,
            'currency'  => $this->currency(),
            'health'    => $health,
            'modes'     => ProviderBillingService::MODES,
            'payBlocker' => $billing->payBlocker($invoice),
            'mine'      => (int) $invoice->captured_by === (int) auth()->id(),
            'canPay'    => TenantAccess::can('invoices.pay'),
            'canReject' => TenantAccess::can('invoices.reject'),
            'canDecide' => TenantAccess::can('invoices.lines.decide'),
            'canDoc'    => TenantAccess::can('invoices.document'),
            'canClaim'  => TenantAccess::can('claims.show'),
        ]);
    }

    /** Contrôle : une ligne (accept/reject) ou toutes les lignes en attente acceptées d'un coup. */
    public function decideLines(Request $request, ProviderInvoice $invoice, ProviderBillingService $billing)
    {
        $data = $request->validate([
            'line' => ['nullable', 'integer'], 'decision' => ['required', 'in:accepted,rejected,accept_all'], 'note' => ['nullable', 'string', 'max:500'],
        ]);
        if ($data['decision'] === 'accept_all') {
            $decisions = $invoice->lines()->where('status', 'pending')->pluck('id')->mapWithKeys(fn ($id) => [$id => ['accepted', null]])->all();
        } else {
            abort_unless($data['line'] ?? null, 422);
            $decisions = [(int) $data['line'] => [$data['decision'], $data['note'] ?? null]];
        }
        try {
            $r = $billing->decideLines($invoice, $decisions, $request->user());
        } catch (AccountingRefusal $e) {
            return back()->with('error', $e->getMessage());
        }
        $cur = $this->currency();
        $msg = $r['done']
            ? ($r['accepted'] > 0 ? 'Contrôle terminé : facture validée pour ' . $cur->format($r['amount']) . ' (' . $r['accepted'] . ' ligne(s) acceptée(s), ' . $r['rejected'] . ' rejetée(s)). Elle attend maintenant le paiement par le trésorier.'
                : 'Contrôle terminé : toutes les lignes sont rejetées, la facture est rejetée. L’établissement voit les motifs et peut renvoyer.')
            : 'Décision enregistrée.';

        return back()->with('success', $msg);
    }

    /** Paiement EFFECTUÉ hors plateforme (trésorier), avec sa référence et sa date. */
    public function pay(Request $request, ProviderInvoice $invoice, ProviderBillingService $billing)
    {
        $data = $request->validate([
            'mode' => ['required', 'in:' . implode(',', array_keys(ProviderBillingService::MODES))],
            'payment_reference' => ['required', 'string', 'min:3', 'max:60'],
            'paid_on' => ['required', 'date'],
        ], ['payment_reference.required' => 'Indiquez la référence du paiement effectué (n° de virement, de transaction ou de reçu).']);
        try {
            $billing->pay($invoice, $request->user(), $data['mode'], $data['payment_reference'], $data['paid_on']);
        } catch (AccountingRefusal $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Paiement de la facture ' . $invoice->label() . ' enregistré (' . $this->currency()->format($invoice->fresh()->amount_minor) . '). L’établissement est prévenu.');
    }

    /** Facture PDF jointe par le prestataire (réservée aux rôles qui voient le détail médical). */
    public function document(ProviderInvoice $invoice, \App\Services\Tenant\InvoiceDocumentService $docs)
    {
        \App\Support\HealthAccessLog::record('invoice_document', $invoice->id);

        return $docs->download($invoice);
    }

    /** Rejet motivé (facture erronée, doublon…) : jamais par la personne qui l'a saisie. */
    public function reject(Request $request, ProviderInvoice $invoice, ProviderBillingService $billing)
    {
        abort_unless(in_array($invoice->status, ProviderInvoice::PAYABLE, true), 422, 'Cette facture n’est plus en attente de paiement.');
        if ((int) $invoice->captured_by === (int) $request->user()->id) {
            return back()->with('error', 'Vous avez saisi cette facture : une autre personne doit décider (contrôle « 4 yeux »).');
        }
        $data = $request->validate(['note' => ['required', 'string', 'min:5', 'max:500']],
            ['note.required' => 'Indiquez le motif du rejet : il est communiqué au prestataire.', 'note.min' => 'Précisez le motif du rejet.']);
        try {
            $billing->reject($invoice, $request->user(), trim($data['note']));
        } catch (AccountingRefusal $e) {
            return back()->with('error', $e->getMessage());
        }

        \App\Support\ProviderNotifier::notify((int) $invoice->provider_id, 'invoice_rejected', 'Facture ' . $invoice->label() . ' rejetée',
            'Montant : ' . \App\Support\ProviderNotifier::money((int) $invoice->amount_minor) . ' · motif : ' . trim($data['note']), 'invoices', 'invoices.view');

        return back()->with('success', 'Facture ' . $invoice->label() . ' rejetée. Le prestataire voit le motif dans son portail'
            . ($invoice->hasLines() ? ' et peut renvoyer les prestations dans une nouvelle facture.' : '.'));
    }

    /**
     * Export CSV (tableur) des factures du filtre courant ; avec ?lignes=1, une ligne par prestation
     * facturée (sans l'acte : donnée de santé).
     */
    public function export(Request $request)
    {
        $statut = $this->tab($request);
        $cur = $this->currency();
        $amount = fn ($v) => $v === null ? '' : number_format(((int) $v) / (10 ** $cur->minor_unit), $cur->minor_unit, ',', '');
        $names = CareProvider::on('central')->pluck('name', 'id');
        $rows = $this->query($request, $statut)->with('careClaim.member')->orderBy('id')->get();
        $byLines = $request->boolean('lignes') && ProviderBillingService::ready();

        $out = fopen('php://temp', 'w+');
        fwrite($out, "\xEF\xBB\xBF");   // BOM : accents corrects dans Excel
        if ($byLines) {
            fputcsv($out, ['Facture', 'Prestataire', 'Réf. prestataire', 'Reçue le', 'État de la facture', 'Prestation n°', 'Date des soins', 'Code adhérent',
                'Montant', 'Devise', 'État de la ligne', 'Motif', 'Payée le', 'Réf. du paiement'], ';');
            $lines = ProviderInvoiceLine::with('prestation.careClaim.member')->whereIn('provider_invoice_id', $rows->pluck('id'))->orderBy('provider_invoice_id')->orderBy('id')->get()->groupBy('provider_invoice_id');
            foreach ($rows as $i) {
                foreach ($lines[$i->id] ?? [] as $l) {
                    fputcsv($out, [$i->label(), $names[$i->provider_id] ?? '#' . $i->provider_id, $i->provider_reference, $i->created_at?->format('d/m/Y'),
                        ProviderInvoice::STATUSES[$i->status] ?? $i->status, $l->prestation_id, $l->care_date?->format('d/m/Y'), $l->prestation?->careClaim?->member?->member_code,
                        $amount($l->amount_minor), $cur->code, ProviderInvoiceLine::STATUSES[$l->status] ?? $l->status, $l->decision_note, $i->paid_on?->format('d/m/Y'), $i->payment_reference], ';');
                }
            }
        } else {
            fputcsv($out, ['N°', 'Référence', 'Date', 'Prestataire', 'Réf. prestataire', 'Adhérent', 'Code adhérent', 'Montant demandé', 'Montant validé', 'Devise', 'État',
                'Décision le', 'Motif', 'Payée le', 'Mode', 'Réf. du paiement'], ';');
            foreach ($rows as $i) {
                fputcsv($out, [$i->id, $i->reference, $i->created_at?->format('d/m/Y'), $names[$i->provider_id] ?? '#' . $i->provider_id, $i->provider_reference,
                    trim(($i->careClaim?->member?->last_name ?? '') . ' ' . ($i->careClaim?->member?->first_name ?? '')), $i->careClaim?->member?->member_code,
                    $amount($i->billed_minor ?? $i->amount_minor), $amount($i->status === 'captured' ? null : $i->amount_minor), $cur->code,
                    ProviderInvoice::STATUSES[$i->status] ?? $i->status, $i->decided_at?->format('d/m/Y'), $i->decision_note, $i->paid_on?->format('d/m/Y'),
                    ProviderBillingService::MODES[$i->payment_mode ?? ''] ?? '', $i->payment_reference], ';');
            }
        }
        rewind($out);

        return response(stream_get_contents($out), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="factures-prestataires-' . ($byLines ? 'lignes-' : '') . $statut . '-' . now()->format('Ymd') . '.csv"',
        ])->setPrivate();
    }

    private function tab(Request $request): string
    {
        $t = (string) $request->get('statut');
        if (array_key_exists($t, self::TABS)) {
            return $t;
        }
        // Par défaut : ce que la personne a à faire (contrôle pour A/C, paiement sinon).
        if (TenantAccess::can('invoices.lines.decide') && ProviderBillingService::ready() && $this->scope(ProviderInvoice::query(), 'a-controler')->exists()) {
            return 'a-controler';
        }

        return 'a-payer';
    }

    /** « À contrôler » = factures à lignes non encore validées ; les anciennes factures sans ligne se paient directement. */
    private function scope($q, string $tab)
    {
        $ready = ProviderBillingService::ready();

        return match ($tab) {
            'a-controler' => $ready ? $q->where('status', 'captured')->whereHas('lines') : $q->whereRaw('1 = 0'),
            'a-payer' => $q->where(fn ($w) => $w->whereIn('status', ['processed', 'prepared'])
                ->orWhere(fn ($c) => $ready ? $c->where('status', 'captured')->whereDoesntHave('lines') : $c->where('status', 'captured'))),
            'payees' => $q->where('status', 'paid'),
            default => $q->where('status', 'rejected'),
        };
    }

    private function query(Request $request, string $statut)
    {
        return $this->scope(ProviderInvoice::query(), $statut)
            ->when($request->integer('prestataire'), fn ($q, $p) => $q->where('provider_id', $p))
            ->when($request->date('du'), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->date('au'), fn ($q, $d) => $q->whereDate('created_at', '<=', $d));
    }
}
