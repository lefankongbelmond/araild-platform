<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Concerns\MutualContext;
use App\Models\Central\CareProvider;
use App\Models\Tenant\ProviderInvoice;
use App\Support\TenantAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Registre des factures des prestataires (tiers payant, 2026-11-03) : toutes les factures
 * en un seul écran, au lieu d'ouvrir chaque recours. Le trésorier paie (hors plateforme :
 * MX-CONNECT enregistre le paiement, n'encaisse ni ne verse rien) ; l'administrateur ou le
 * contrôleur peut rejeter une facture, avec un motif visible du prestataire.
 */
class InvoiceRegisterController extends Controller
{
    use MutualContext;

    private const FILTERS = ['a-payer' => ProviderInvoice::PAYABLE, 'payees' => ['paid'], 'rejetees' => ['rejected']];

    public function index(Request $request)
    {
        $statut = array_key_exists((string) $request->get('statut'), self::FILTERS) ? $request->get('statut') : 'a-payer';
        $invoices = $this->query($request, $statut)->with('careClaim.member')->latest('id')->paginate(30)->withQueryString();
        $providerIds = ProviderInvoice::distinct()->pluck('provider_id');

        return view('tenant.invoices.index', [
            'invoices'  => $invoices,
            'statut'    => $statut,
            'currency'  => $this->currency(),
            'providers' => CareProvider::on('central')->whereIn('id', $providerIds)->orderBy('name')->pluck('name', 'id'),
            'totals'    => [
                'a-payer' => [ProviderInvoice::whereIn('status', ProviderInvoice::PAYABLE)->count(), (int) ProviderInvoice::whereIn('status', ProviderInvoice::PAYABLE)->sum('amount_minor')],
                'payees'  => [ProviderInvoice::where('status', 'paid')->count(), (int) ProviderInvoice::where('status', 'paid')->sum('amount_minor')],
                'rejetees' => [ProviderInvoice::where('status', 'rejected')->count(), (int) ProviderInvoice::where('status', 'rejected')->sum('amount_minor')],
            ],
            'canPay'    => TenantAccess::can('claims.invoice.pay'),
            'canReject' => TenantAccess::can('invoices.reject'),
            'canDoc'    => TenantAccess::can('claims.invoice.document'),
            'canClaim'  => TenantAccess::can('claims.show'),
        ]);
    }

    /** Rejet motivé (facture erronée, doublon…) : jamais par la personne qui l'a saisie. */
    public function reject(Request $request, ProviderInvoice $invoice)
    {
        abort_unless(in_array($invoice->status, ProviderInvoice::PAYABLE, true), 422, 'Cette facture n’est plus en attente de paiement.');
        if ((int) $invoice->captured_by === (int) $request->user()->id) {
            return back()->with('error', 'Vous avez saisi cette facture : une autre personne doit décider (contrôle « 4 yeux »).');
        }
        $data = $request->validate(['note' => ['required', 'string', 'min:5', 'max:500']],
            ['note.required' => 'Indiquez le motif du rejet : il est communiqué au prestataire.', 'note.min' => 'Précisez le motif du rejet.']);

        $invoice->update(['status' => 'rejected', 'validated_by' => $request->user()->id]
            + (Schema::hasColumn('provider_invoices', 'decision_note') ? ['decision_note' => trim($data['note']), 'decided_at' => now()] : []));

        return back()->with('success', "Facture n° {$invoice->id} rejetée. Le prestataire voit le motif dans son portail.");
    }

    /** Export CSV (tableur) des factures du filtre courant. */
    public function export(Request $request)
    {
        $statut = array_key_exists((string) $request->get('statut'), self::FILTERS) ? $request->get('statut') : 'a-payer';
        $cur = $this->currency();
        $names = CareProvider::on('central')->pluck('name', 'id');
        $rows = $this->query($request, $statut)->with('careClaim.member')->orderBy('id')->get();

        $out = fopen('php://temp', 'w+');
        fwrite($out, "\xEF\xBB\xBF");   // BOM : accents corrects dans Excel
        fputcsv($out, ['N°', 'Date', 'Prestataire', 'Réf. prestataire', 'Adhérent', 'Code adhérent', 'Montant', 'Devise', 'État', 'Décision le', 'Motif'], ';');
        foreach ($rows as $i) {
            fputcsv($out, [$i->id, $i->created_at?->format('d/m/Y'), $names[$i->provider_id] ?? '#' . $i->provider_id, $i->provider_reference,
                trim(($i->careClaim?->member?->last_name ?? '') . ' ' . ($i->careClaim?->member?->first_name ?? '')), $i->careClaim?->member?->member_code,
                number_format($i->amount_minor / (10 ** $cur->minor_unit), $cur->minor_unit, ',', ''), $cur->code,
                ProviderInvoice::STATUSES[$i->status] ?? $i->status, $i->decided_at?->format('d/m/Y'), $i->decision_note], ';');
        }
        rewind($out);

        return response(stream_get_contents($out), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="factures-prestataires-' . $statut . '-' . now()->format('Ymd') . '.csv"',
        ])->setPrivate();
    }

    private function query(Request $request, string $statut)
    {
        return ProviderInvoice::query()->whereIn('status', self::FILTERS[$statut])
            ->when($request->integer('prestataire'), fn ($q, $p) => $q->where('provider_id', $p))
            ->when($request->date('du'), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->date('au'), fn ($q, $d) => $q->whereDate('created_at', '<=', $d));
    }
}
