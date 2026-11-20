<?php

namespace App\Services\Tenant;

use App\Models\Tenant\AccountingDay;
use App\Models\Tenant\InvoiceEvent;
use App\Models\Tenant\Prestation;
use App\Models\Tenant\ProviderInvoice;
use App\Models\Tenant\ProviderInvoiceLine;
use App\Models\Tenant\TreasuryMovement;
use App\Models\Tenant\User;
use App\Services\AccountingRefusal;
use App\Services\AccountingService;
use App\Support\Msg;
use App\Support\ProviderNotifier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Facturation des établissements de soins (portail prestataire V2, phase 5 ; 2026-11-26).
 *
 * Circuit, dans la base de la mutuelle :
 *  1. L'établissement envoie une FACTURE À LIGNES : un bordereau de plusieurs prestations validées
 *     (ou une seule prise en charge). Une prestation n'est jamais facturée deux fois : elle est
 *     verrouillée pendant l'envoi et ne peut figurer que dans une seule ligne « active ».
 *  2. La mutuelle CONTRÔLE chaque ligne (acceptée, ou rejetée avec un motif) ; jamais par la personne
 *     qui a saisi la facture. Quand plus aucune ligne n'attend, la facture est VALIDÉE pour le montant
 *     des lignes acceptées (ou rejetée si aucune ne l'est). Une ligne rejetée redevient facturable :
 *     l'établissement la corrige et la RENVOIE dans un nouveau bordereau.
 *  3. Le trésorier enregistre le PAIEMENT seulement quand il a été effectué (référence du virement,
 *     de la transaction Mobile Money ou du reçu de caisse, date) : validée ≠ payée. MX-CONNECT ne
 *     verse rien ; il enregistre la sortie de trésorerie.
 * Les factures antérieures, sans ligne, gardent leur fonctionnement (payables directement).
 */
class ProviderBillingService
{
    public const MODES = ['bank' => 'Virement bancaire', 'mobile_money' => 'Mobile Money', 'cash' => 'Espèces'];
    public const MAX_LINES = 200;

    private static array $ready = [];

    public function __construct(private AccountingService $accounting, private InvoiceDocumentService $docs) {}

    /** Migration 2026_11_26 appliquée dans cette mutuelle ? */
    public static function ready(): bool
    {
        $key = (string) (function_exists('tenant') ? tenant('id') : '');

        return self::$ready[$key] ??= Schema::hasTable('provider_invoice_lines');
    }

    /**
     * Prestations de cet établissement qui peuvent être facturées : validées, avec une part de la
     * mutuelle, absentes de toute ligne active, et hors des prises en charge déjà couvertes par une
     * ancienne facture sans ligne (non rejetée).
     */
    public function billable(int $providerId)
    {
        $q = Prestation::query()->where('provider_id', $providerId)->where('status', 'validated')->where('mutual_part_minor', '>', 0);
        if (self::ready()) {
            $q->whereNotIn('id', ProviderInvoiceLine::query()->whereIn('status', ProviderInvoiceLine::ACTIVE)
                ->whereHas('invoice', fn ($i) => $i->where('status', '!=', 'rejected'))->select('prestation_id'));
        }
        $legacy = ProviderInvoice::query()->where('provider_id', $providerId)->whereNotNull('care_claim_id')->where('status', '!=', 'rejected');
        if (self::ready()) {
            $legacy->whereDoesntHave('lines');
        }

        return $q->where(fn ($w) => $w->whereNull('care_claim_id')->orWhereNotIn('care_claim_id', $legacy->select('care_claim_id')));
    }

    /** Prestations déjà rejetées sur une facture précédente (pour signaler un « renvoi »). */
    public function previousRejections(array $prestationIds): array
    {
        if (! self::ready() || ! $prestationIds) {
            return [];
        }

        return ProviderInvoiceLine::with('invoice:id,reference')->whereIn('prestation_id', $prestationIds)->where('status', 'rejected')
            ->latest('id')->get()->unique('prestation_id')
            ->mapWithKeys(fn ($l) => [$l->prestation_id => ['ref' => $l->invoice?->reference ?? ('n° ' . $l->provider_invoice_id), 'note' => $l->decision_note]])->all();
    }

    /**
     * Envoi d'une facture à lignes (bordereau ou une prise en charge).
     *
     * @param  int[]  $prestationIds
     */
    public function submit(int $providerId, array $prestationIds, User $by, ?string $providerReference, ?UploadedFile $document, string $kind = 'batch'): ProviderInvoice
    {
        $ids = array_values(array_unique(array_map('intval', $prestationIds)));
        if (! $ids) {
            throw new AccountingRefusal(__('Cochez au moins une prestation à facturer.'));
        }
        if (count($ids) > self::MAX_LINES) {
            throw new AccountingRefusal(__('Un bordereau compte au plus :max prestations : envoyez-en plusieurs.', ['max' => self::MAX_LINES]));
        }

        return DB::transaction(function () use ($providerId, $ids, $by, $providerReference, $document, $kind) {
            // Verrou sur les prestations : deux envois simultanés (double clic, deux postes) sont
            // traités l'un après l'autre, et le second ne retrouve plus ces prestations « à facturer ».
            $locked = Prestation::whereIn('id', $ids)->where('provider_id', $providerId)->lockForUpdate()->get();
            $ok = $this->billable($providerId)->whereIn('id', $ids)->orderBy('care_date')->orderBy('id')->get();
            if ($locked->count() !== count($ids) || $ok->count() !== count($ids)) {
                throw new AccountingRefusal(__('Une ou plusieurs prestations ne sont plus à facturer (déjà facturées, ou non validées). Actualisez la page.'));
            }

            $total = (int) $ok->sum('mutual_part_minor');
            $claims = $ok->pluck('care_claim_id')->unique()->filter()->values();
            $invoice = ProviderInvoice::create([
                'provider_id' => $providerId, 'care_claim_id' => $claims->count() === 1 ? $claims->first() : null, 'kind' => $kind,
                'amount_minor' => $total, 'billed_minor' => $total, 'status' => 'captured', 'captured_by' => $by->id,
                'provider_reference' => $providerReference !== null && trim($providerReference) !== '' ? trim($providerReference) : null,
            ]);
            $invoice->update(['reference' => 'BOR-' . now()->format('Y') . '-' . str_pad((string) $invoice->id, 5, '0', STR_PAD_LEFT)]);
            foreach ($ok as $p) {
                ProviderInvoiceLine::create([
                    'provider_invoice_id' => $invoice->id, 'prestation_id' => $p->id, 'care_claim_id' => $p->care_claim_id,
                    'care_date' => $p->care_date?->toDateString(), 'amount_minor' => (int) $p->mutual_part_minor, 'status' => 'pending',
                ]);
            }
            $this->event($invoice, 'sent', $by->id, $total, $ok->count() . ' prestation(s)');
            if ($document) {
                $this->docs->attach($invoice, $document);
                $this->event($invoice, 'document', $by->id);
            }

            return $invoice;
        });
    }

    /**
     * Décision sur des lignes en attente : [ligne => ['accepted'|'rejected', motif]].
     *
     * @return array{done:bool, accepted:int, rejected:int, amount:int}
     */
    public function decideLines(ProviderInvoice $invoice, array $decisions, User $by): array
    {
        if ((int) $invoice->captured_by === (int) $by->id) {
            throw new AccountingRefusal(__('Vous avez saisi cette facture : une autre personne doit la contrôler (contrôle « 4 yeux »).'));
        }

        $result = DB::transaction(function () use ($invoice, $decisions, $by) {
            $inv = ProviderInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ($inv->status !== 'captured') {
                throw new AccountingRefusal(__('Cette facture n’est plus à contrôler.'));
            }
            $lines = $inv->lines()->where('status', 'pending')->whereIn('id', array_map('intval', array_keys($decisions)))->get()->keyBy('id');
            if ($lines->isEmpty()) {
                throw new AccountingRefusal(__('Aucune ligne à contrôler dans votre choix.'));
            }
            foreach ($lines as $id => $line) {
                [$decision, $note] = $decisions[$id] + [1 => null];
                $note = $note !== null ? trim((string) $note) : null;
                if ($decision === 'accepted') {
                    $p = Prestation::find($line->prestation_id);
                    if (! $p || $p->status !== 'validated' || (int) $p->provider_id !== (int) $inv->provider_id) {
                        throw new AccountingRefusal(__('La prestation n° :id n’est plus validée : rejetez cette ligne avec un motif.', ['id' => $line->prestation_id]));
                    }
                } elseif ($decision === 'rejected') {
                    if (mb_strlen((string) $note) < 5) {
                        throw new AccountingRefusal(__('Indiquez le motif de chaque ligne rejetée (5 caractères au moins) : il est communiqué à l’établissement.'));
                    }
                } else {
                    continue;
                }
                $line->update(['status' => $decision, 'decision_note' => $decision === 'rejected' ? mb_substr($note, 0, 500) : null,
                    'decided_by' => $by->id, 'decided_at' => now()]);
            }

            return $this->closeIfDecided($inv, $by);
        });

        if ($result['done']) {
            $ref = $invoice->reference ?? Msg::of('n° :id', ['id' => $invoice->id]);
            if ($result['accepted'] > 0) {
                $body = [Msg::of('Montant accepté : :amount (:count ligne(s))', ['amount' => ProviderNotifier::money($result['amount']), 'count' => $result['accepted']])];
                if ($result['rejected']) {
                    $body[] = Msg::of(':count ligne(s) rejetée(s), à corriger et renvoyer : motifs dans le détail de la facture.', ['count' => $result['rejected']]);
                }
                ProviderNotifier::notify((int) $invoice->provider_id, 'invoice_checked', Msg::of('Facture :ref validée, en attente de paiement', ['ref' => $ref]),
                    $body, 'invoices', 'invoices.view');
            } else {
                ProviderNotifier::notify((int) $invoice->provider_id, 'invoice_rejected', Msg::of('Facture :ref rejetée', ['ref' => $ref]),
                    Msg::of('Toutes les lignes ont été rejetées : motifs dans le détail de la facture. Vous pouvez les corriger et les renvoyer.'), 'invoices', 'invoices.view');
            }
        }

        return $result;
    }

    /** Rejet de toute la facture (doublon, pièce illisible…) : ses lignes redeviennent facturables. */
    public function reject(ProviderInvoice $invoice, User $by, string $note): void
    {
        if ((int) $invoice->captured_by === (int) $by->id) {
            throw new AccountingRefusal(__('Vous avez saisi cette facture : une autre personne doit décider (contrôle « 4 yeux »).'));
        }
        DB::transaction(function () use ($invoice, $by, $note) {
            $inv = ProviderInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if (! in_array($inv->status, ProviderInvoice::PAYABLE, true)) {
                throw new AccountingRefusal(__('Cette facture n’est plus en attente de paiement.'));
            }
            $inv->update(['status' => 'rejected', 'validated_by' => $by->id]
                + (Schema::hasColumn('provider_invoices', 'decision_note') ? ['decision_note' => $note, 'decided_at' => now()] : []));
            if (self::ready()) {
                $inv->lines()->whereIn('status', ProviderInvoiceLine::ACTIVE)
                    ->update(['status' => 'rejected', 'decision_note' => mb_substr('Facture rejetée : ' . $note, 0, 500), 'decided_by' => $by->id, 'decided_at' => now()]);
                $this->event($inv, 'rejected', $by->id, (int) $inv->amount_minor, $note);
            }
        });
    }

    /** La facture peut-elle être payée maintenant ? (texte du motif sinon) */
    public function payBlocker(ProviderInvoice $invoice): ?string
    {
        if ($invoice->status === 'paid') {
            return __('Cette facture est déjà payée.');
        }
        if (! in_array($invoice->status, ProviderInvoice::PAYABLE, true)) {
            return __('Cette facture n’est pas à payer.');
        }
        if ($invoice->hasLines() && $invoice->status === 'captured') {
            return __('Contrôlez d’abord les lignes de cette facture : seule une facture validée se paie.');
        }

        return null;
    }

    /** Enregistrement d'un paiement EFFECTUÉ (hors plateforme), avec sa référence. */
    public function pay(ProviderInvoice $invoice, User $by, string $mode, ?string $reference, string $date): void
    {
        $reference = trim((string) $reference);
        $day = Carbon::parse($date)->startOfDay();
        if (! array_key_exists($mode, self::MODES)) {
            throw new AccountingRefusal(__('Mode de paiement inconnu.'));
        }
        if (mb_strlen($reference) < 3) {
            throw new AccountingRefusal(__('Indiquez la référence du paiement effectué (n° de virement, de transaction Mobile Money ou de reçu de caisse).'));
        }
        if ($day->gt(now()->startOfDay())) {
            throw new AccountingRefusal(__('La date du paiement ne peut pas être dans le futur : enregistrez le paiement une fois effectué.'));
        }
        if ($this->accounting->isYearClosed((int) $day->year)) {
            throw new AccountingRefusal(__('L’exercice :year est clôturé : choisissez une date dans un exercice ouvert.', ['year' => $day->year]));
        }
        if (AccountingDay::whereDate('day', $day->toDateString())->first()?->isClosed()) {
            throw new AccountingRefusal(__('La journée du :date est arrêtée (arrêté de caisse) : choisissez une autre date.', ['date' => $day->format('d/m/Y')]));
        }
        if ((int) $invoice->captured_by === (int) $by->id || ($invoice->checked_by && (int) $invoice->checked_by === (int) $by->id)) {
            throw new AccountingRefusal(__('Vous avez saisi ou contrôlé cette facture : une autre personne doit enregistrer le paiement (contrôle « 4 yeux »).'));
        }

        $paid = DB::transaction(function () use ($invoice, $by, $mode, $reference, $day) {
            $inv = ProviderInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ($why = $this->payBlocker($inv)) {
                throw new AccountingRefusal($why);
            }
            $inv->update(['status' => 'paid', 'validated_by' => $by->id]
                + (Schema::hasColumn('provider_invoices', 'decided_at') ? ['decided_at' => now()] : [])
                + (self::ready() ? ['payment_reference' => mb_substr($reference, 0, 60), 'paid_on' => $day->toDateString(), 'payment_mode' => $mode] : []));
            TreasuryMovement::create([
                'direction' => 'outflow', 'source' => 'provider_invoice', 'amount_minor' => $inv->amount_minor, 'mode' => $mode,
                'linked_reference' => mb_substr($inv->reference ?? ('INV-' . $inv->id), 0, 60), 'moved_on' => $day->toDateString(),
            ]);
            if (self::ready()) {
                $this->event($inv, 'paid', $by->id, (int) $inv->amount_minor, self::MODES[$mode] . ' · réf. ' . $reference);
            }

            return $inv;
        });

        ProviderNotifier::notify((int) $paid->provider_id, 'invoice_paid',
            Msg::of('Facture :ref payée', ['ref' => $paid->reference ?? Msg::of('n° :id', ['id' => $paid->id])]),
            [Msg::of('Montant : :amount', ['amount' => ProviderNotifier::money((int) $paid->amount_minor)]),
                Msg::of(':mode du :date', ['mode' => ['__' => self::MODES[$mode]], 'date' => $day->format('d/m/Y')]),
                Msg::of('réf. :ref', ['ref' => $reference])],
            'invoices', 'invoices.view');
    }

    public function event(ProviderInvoice $invoice, string $event, ?int $userId, ?int $amount = null, ?string $note = null): void
    {
        if (self::ready()) {
            InvoiceEvent::create(['provider_invoice_id' => $invoice->id, 'event' => $event, 'user_id' => $userId,
                'amount_minor' => $amount, 'note' => $note !== null ? mb_substr($note, 0, 500) : null]);
        }
    }

    /** Plus aucune ligne en attente → facture validée (lignes acceptées) ou rejetée (aucune). */
    private function closeIfDecided(ProviderInvoice $inv, User $by): array
    {
        $counts = $inv->lines()->selectRaw('status, count(*) n, sum(amount_minor) s')->groupBy('status')->get()->keyBy('status');
        $accepted = (int) ($counts['accepted']->n ?? 0);
        $rejected = (int) ($counts['rejected']->n ?? 0);
        $amount = (int) ($counts['accepted']->s ?? 0);
        if ((int) ($counts['pending']->n ?? 0) > 0) {
            return ['done' => false, 'accepted' => $accepted, 'rejected' => $rejected, 'amount' => $amount];
        }
        $summary = $accepted . ' ligne(s) acceptée(s), ' . $rejected . ' rejetée(s)';
        if ($accepted > 0) {
            $inv->update(['status' => 'processed', 'amount_minor' => $amount, 'checked_by' => $by->id, 'checked_at' => now()]);
            $this->event($inv, 'checked', $by->id, $amount, $summary);
        } else {
            $inv->update(['status' => 'rejected', 'amount_minor' => 0, 'checked_by' => $by->id, 'checked_at' => now(), 'validated_by' => $by->id,
                'decision_note' => 'Toutes les lignes ont été rejetées (motifs dans le détail).', 'decided_at' => now()]);
            $this->event($inv, 'rejected', $by->id, 0, $summary);
        }

        return ['done' => true, 'accepted' => $accepted, 'rejected' => $rejected, 'amount' => $amount];
    }
}
