<?php

namespace App\Services;

use App\Models\Tenant\Account;
use App\Models\Tenant\AccountingDay;
use App\Models\Tenant\AccountingEntry;
use App\Models\Tenant\AccountingPeriod;
use App\Models\Tenant\FiscalYear;
use App\Models\Tenant\TreasuryMovement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * SYSCOHADA accounting engine (CDC §19).
 *
 * Posts double-entry lines and bridges treasury movements into the journal using
 * the mapping in config('mxconnect.accounting'). Every entry carries a single
 * amount against one debit and one credit account, so it is balanced by design.
 * Entries cannot be posted onto a closed accounting day.
 */
class AccountingService
{
    /** Resolve (creating if needed) the accounting day for a date. */
    public function resolveDay(string $date): AccountingDay
    {
        $carbon = Carbon::parse($date);
        $year = (int) $carbon->year;

        $fy = FiscalYear::firstOrCreate(
            ['label' => (string) $year],
            ['start_date' => "{$year}-01-01", 'end_date' => "{$year}-12-31", 'status' => 'open'],
        );

        $period = AccountingPeriod::firstOrCreate(
            ['fiscal_year_id' => $fy->id, 'label' => $carbon->format('Y-m')],
            ['status' => 'open'],
        );

        return AccountingDay::firstOrCreate(
            ['accounting_period_id' => $period->id, 'day' => $carbon->toDateString()],
            ['status' => 'open'],
        );
    }

    /** Post a balanced entry. Throws if the target day is closed. */
    public function post(string $date, string $debitNumber, string $creditNumber, int $amountMinor, string $label, ?string $piece = null): AccountingEntry
    {
        if ($this->isYearClosed((int) Carbon::parse($date)->year)) {
            throw new \RuntimeException('Exercice ' . Carbon::parse($date)->year . ' clôturé.');
        }
        $day = $this->resolveDay($date);
        if ($day->isClosed()) {
            throw new \RuntimeException("Accounting day {$day->day->toDateString()} is closed.");
        }

        $debit  = Account::byNumber($debitNumber)  ?? throw new \RuntimeException("Unknown account {$debitNumber}");
        $credit = Account::byNumber($creditNumber) ?? throw new \RuntimeException("Unknown account {$creditNumber}");

        return AccountingEntry::create([
            'accounting_day_id' => $day->id,
            'piece'             => $piece ?: 'PIECE-' . strtoupper(Str::random(6)),
            'debit_account_id'  => $debit->id,
            'credit_account_id' => $credit->id,
            'amount_minor'      => $amountMinor,
            'label'             => $label,
            'status'            => 'validated',
        ]);
    }

    /**
     * Bridge a treasury movement into the journal.
     *  - inflow : debit the mode's treasury account, credit the source income account.
     *  - outflow: debit the source expense account, credit the mode's treasury account.
     * Idempotent: a movement already linked to a day is skipped (returns null).
     */
    public function postFromMovement(TreasuryMovement $movement): ?AccountingEntry
    {
        if ($movement->accounting_day_id) {
            return null; // already posted
        }

        $cfg = config('mxconnect.accounting');
        $treasury = $cfg['mode_account'][$movement->mode] ?? $cfg['mode_account']['cash'];

        if ($movement->direction === 'inflow') {
            $income = $cfg['source_income'][$movement->source] ?? $cfg['source_income']['other'];
            $entry = $this->post(
                $movement->moved_on->toDateString(),
                debitNumber: $treasury, creditNumber: $income,
                amountMinor: $movement->amount_minor,
                label: "Encaissement {$movement->source}",
                piece: $movement->linked_reference,
            );
        } else {
            $expense = $cfg['source_expense'][$movement->source] ?? $cfg['source_expense']['other'];
            $entry = $this->post(
                $movement->moved_on->toDateString(),
                debitNumber: $expense, creditNumber: $treasury,
                amountMinor: $movement->amount_minor,
                label: in_array($movement->source, ['contribution', 'membership_fee'], true)
                    ? 'Annulation d’encaissement ' . preg_replace('/^ANN-/', '', (string) $movement->linked_reference)
                    : "Décaissement {$movement->source}",
                piece: $movement->linked_reference,
            );
        }

        $movement->update(['accounting_day_id' => $entry->accounting_day_id]);

        return $entry;
    }

    // ------------------------------------------------------------------------------------
    // Opérations diverses, extournes, clôture d'exercice (2026-11-05).
    // Règle « 4 yeux » : une écriture saisie à la main n'entre dans les états qu'une fois
    // validée par une AUTRE personne. Un exercice clôturé n'accepte plus aucune écriture.
    // ------------------------------------------------------------------------------------

    /** Comptes créés au besoin par la clôture (SYSCOHADA). */
    public const CLOSING_ACCOUNTS = [
        '130' => 'Résultat en instance d’affectation',
        '131' => 'Résultat net : bénéfice',
        '139' => 'Résultat net : perte',
    ];

    public function isYearClosed(int $year): bool
    {
        return FiscalYear::where('label', (string) $year)->where('status', 'closed')->exists();
    }

    /** Refus lisible (message affiché tel quel à l'utilisateur). */
    private function refuse(string $message): never
    {
        throw new AccountingRefusal($message);
    }

    /** Contrôles communs à une date d'écriture : exercice ouvert, journée non arrêtée. */
    private function openDay(string $date): AccountingDay
    {
        $year = (int) Carbon::parse($date)->year;
        if ($this->isYearClosed($year)) {
            $this->refuse("L’exercice {$year} est clôturé : aucune écriture ne peut y être passée.");
        }
        $day = $this->resolveDay($date);
        if ($day->isClosed()) {
            $this->refuse('La journée du ' . $day->day->format('d/m/Y') . ' est arrêtée (arrêté de caisse) : choisissez une autre date.');
        }

        return $day;
    }

    /** Saisie d'une opération diverse : elle attend la validation d'une autre personne. */
    public function capture(string $date, Account $debit, Account $credit, int $amountMinor, string $label, ?string $piece, int $userId): AccountingEntry
    {
        if ($debit->id === $credit->id) {
            $this->refuse('Le compte débité et le compte crédité doivent être différents.');
        }
        if ($amountMinor <= 0) {
            $this->refuse('Le montant doit être supérieur à zéro.');
        }
        $day = $this->openDay($date);

        return AccountingEntry::create([
            'accounting_day_id' => $day->id,
            'piece'             => $piece ?: 'OD-' . $day->day->format('Ymd') . '-' . strtoupper(Str::random(4)),
            'debit_account_id'  => $debit->id,
            'credit_account_id' => $credit->id,
            'amount_minor'      => $amountMinor,
            'label'             => $label,
            'status'            => 'captured',
            'kind'              => 'manual',
            'captured_by'       => $userId,
        ]);
    }

    /** Validation par une autre personne que la saisie. */
    public function validateEntry(AccountingEntry $entry, int $userId): void
    {
        if ($entry->status !== 'captured') {
            $this->refuse('Cette écriture n’est plus en attente de validation.');
        }
        if ((int) $entry->captured_by === $userId) {
            $this->refuse('Vous avez saisi cette écriture : une autre personne doit la valider (contrôle « 4 yeux »).');
        }
        $this->openDay($entry->accountingDay->day->toDateString());
        if ($entry->kind === 'reversal' && $entry->reversed_entry_id && $this->reversalOf($entry->reversed_entry_id, $entry->id)) {
            $this->refuse('Cette écriture a déjà été extournée.');
        }
        $entry->update(['status' => 'validated', 'validated_by' => $userId, 'validated_at' => now()]);
    }

    public function rejectEntry(AccountingEntry $entry, int $userId, string $note): void
    {
        if ($entry->status !== 'captured') {
            $this->refuse('Cette écriture n’est plus en attente de validation.');
        }
        if ((int) $entry->captured_by === $userId) {
            $this->refuse('Vous avez saisi cette écriture : une autre personne doit décider (contrôle « 4 yeux »).');
        }
        $entry->update(['status' => 'rejected', 'validated_by' => $userId, 'validated_at' => now(), 'decision_note' => $note]);
    }

    /** Extourne d'une écriture validée : contre-passation (débit et crédit inversés), à valider. */
    public function reverse(AccountingEntry $entry, string $date, int $userId): AccountingEntry
    {
        if (! in_array($entry->status, ['validated', 'posted'], true)) {
            $this->refuse('Seule une écriture validée peut être extournée.');
        }
        if (in_array($entry->kind, ['closing', 'reversal'], true)) {
            $this->refuse($entry->kind === 'closing' ? 'Une écriture de clôture ne s’extourne pas : rouvrez l’exercice.' : 'Une extourne ne s’extourne pas : saisissez une nouvelle opération.');
        }
        if ($this->reversalOf($entry->id)) {
            $this->refuse('Cette écriture a déjà une extourne (validée ou en attente).');
        }
        $day = $this->openDay($date);

        return AccountingEntry::create([
            'accounting_day_id' => $day->id,
            'piece'             => 'EXT-' . $entry->piece,
            'debit_account_id'  => $entry->credit_account_id,
            'credit_account_id' => $entry->debit_account_id,
            'amount_minor'      => $entry->amount_minor,
            'label'             => 'Extourne : ' . $entry->label,
            'status'            => 'captured',
            'kind'              => 'reversal',
            'reversed_entry_id' => $entry->id,
            'captured_by'       => $userId,
        ]);
    }

    /** Extourne existante (non rejetée) d'une écriture. */
    public function reversalOf(int $entryId, ?int $except = null): ?AccountingEntry
    {
        return AccountingEntry::where('reversed_entry_id', $entryId)->where('kind', 'reversal')
            ->whereIn('status', $except ? ['validated', 'posted'] : ['captured', 'validated', 'posted'])
            ->when($except, fn ($q) => $q->where('id', '!=', $except))->first();
    }

    /**
     * Ce qui empêche de clôturer un exercice (liste vide = clôture possible).
     *
     * @return list<string>
     */
    public function closingBlockers(int $year): array
    {
        $out = [];
        if ($year >= (int) now()->year) {
            $out[] = "L’exercice {$year} n’est pas terminé (clôture possible à partir du 1er janvier " . ($year + 1) . ').';
        }
        if ($this->isYearClosed($year)) {
            $out[] = "L’exercice {$year} est déjà clôturé.";
        }
        $pending = AccountingEntry::where('status', 'captured')->whereHas('accountingDay', fn ($q) => $q->whereYear('day', $year))->count();
        if ($pending) {
            $out[] = "{$pending} écriture(s) de {$year} attendent leur validation : validez-les ou rejetez-les.";
        }
        $unposted = TreasuryMovement::whereNull('accounting_day_id')->whereYear('moved_on', $year)->count();
        if ($unposted) {
            $out[] = "{$unposted} mouvement(s) de trésorerie de {$year} ne sont pas comptabilisés (Trésorerie → « Comptabiliser maintenant »).";
        }
        if ($year > 2000 && ! $this->isYearClosed($year - 1)
            && AccountingEntry::whereIn('status', ['validated', 'posted'])->whereHas('accountingDay', fn ($q) => $q->whereYear('day', $year - 1))->exists()) {
            $out[] = 'L’exercice ' . ($year - 1) . ' doit être clôturé d’abord.';
        }

        return $out;
    }

    /**
     * Clôture : solde les comptes de gestion (classes 6, 7, 8) de l'exercice dans le compte 130,
     * puis porte le résultat en 131 (bénéfice) ou 139 (perte) ; écritures datées du 31/12,
     * marquées « closing ». L'exercice est ensuite verrouillé.
     *
     * @return int résultat de l'exercice (unités mineures ; négatif = perte)
     */
    public function closeYear(int $year, int $userId): int
    {
        if ($blockers = $this->closingBlockers($year)) {
            $this->refuse($blockers[0]);
        }

        return DB::transaction(function () use ($year, $userId) {
            foreach (self::CLOSING_ACCOUNTS as $number => $label) {
                Account::firstOrCreate(['number' => (string) $number], ['label' => $label, 'class' => 1]);
            }
            $a130 = Account::byNumber('130');
            $day = $this->resolveDay("{$year}-12-31");   // même une journée arrêtée : la clôture n'est pas un mouvement de caisse
            $g = DB::connection()->getQueryGrammar();
            $base = fn () => DB::table('accounting_entries as e')->join('accounting_days as d', 'd.id', '=', 'e.accounting_day_id')
                ->whereIn('e.status', ['validated', 'posted'])->whereYear('d.day', $year);
            $debits = $base()->selectRaw($g->wrap('e.debit_account_id') . ' as a, sum(' . $g->wrap('e.amount_minor') . ') as s')->groupBy('e.debit_account_id')->pluck('s', 'a');
            $credits = $base()->selectRaw($g->wrap('e.credit_account_id') . ' as a, sum(' . $g->wrap('e.amount_minor') . ') as s')->groupBy('e.credit_account_id')->pluck('s', 'a');

            $result = 0;
            $make = fn (int $debit, int $credit, int $amount, string $label) => AccountingEntry::create([
                'accounting_day_id' => $day->id, 'piece' => "CLOT-{$year}", 'debit_account_id' => $debit, 'credit_account_id' => $credit,
                'amount_minor' => $amount, 'label' => $label, 'status' => 'validated', 'kind' => 'closing',
                'captured_by' => $userId, 'validated_by' => $userId, 'validated_at' => now(),
            ]);
            foreach (Account::whereIn('class', [6, 7, 8])->orderBy('number')->get() as $a) {
                $net = (int) ($debits[$a->id] ?? 0) - (int) ($credits[$a->id] ?? 0);   // > 0 : solde débiteur (charge)
                if ($net > 0) {
                    $make($a130->id, $a->id, $net, "Clôture {$year} : solde du compte {$a->number}");
                } elseif ($net < 0) {
                    $make($a->id, $a130->id, -$net, "Clôture {$year} : solde du compte {$a->number}");
                }
                $result -= $net;
            }
            if ($result > 0) {
                $make($a130->id, Account::byNumber('131')->id, $result, "Clôture {$year} : bénéfice de l’exercice");
            } elseif ($result < 0) {
                $make(Account::byNumber('139')->id, $a130->id, -$result, "Clôture {$year} : perte de l’exercice");
            }

            $fy = FiscalYear::firstOrCreate(['label' => (string) $year], ['start_date' => "{$year}-01-01", 'end_date' => "{$year}-12-31", 'status' => 'open']);
            $history = $fy->history ?? [];
            $history[] = ['action' => 'close', 'at' => now()->toIso8601String(), 'by' => $userId, 'result' => $result];
            $fy->update(['status' => 'closed', 'closed_at' => now(), 'closed_by' => $userId, 'result_minor' => $result, 'history' => $history]);

            return $result;
        });
    }

    /** Réouverture (administrateur, motif) : supprime les écritures de clôture, si le résultat n'a pas encore été affecté. */
    public function reopenYear(int $year, int $userId, string $note): void
    {
        $fy = FiscalYear::where('label', (string) $year)->first();
        if (! $fy || $fy->status !== 'closed') {
            $this->refuse("L’exercice {$year} n’est pas clôturé.");
        }
        if ($this->isYearClosed($year + 1)) {
            $this->refuse('L’exercice ' . ($year + 1) . ' est clôturé : rouvrez-le d’abord.');
        }
        $resultAccounts = Account::whereIn('number', array_keys(self::CLOSING_ACCOUNTS))->pluck('id');
        $used = AccountingEntry::where('kind', '!=', 'closing')->whereIn('status', ['captured', 'validated', 'posted'])
            ->where(fn ($q) => $q->whereIn('debit_account_id', $resultAccounts)->orWhereIn('credit_account_id', $resultAccounts))
            ->whereHas('accountingDay', fn ($q) => $q->where('day', '>', "{$year}-12-31"))->exists();
        if ($used) {
            $this->refuse('Le résultat de ' . $year . ' a déjà été affecté (écriture sur les comptes 130, 131 ou 139) : extournez d’abord cette affectation.');
        }

        DB::transaction(function () use ($fy, $year, $userId, $note) {
            AccountingEntry::where('kind', 'closing')->where('piece', "CLOT-{$year}")->delete();
            $history = $fy->history ?? [];
            $history[] = ['action' => 'reopen', 'at' => now()->toIso8601String(), 'by' => $userId, 'note' => $note];
            $fy->update(['status' => 'open', 'closed_at' => null, 'closed_by' => null, 'result_minor' => null, 'history' => $history]);
        });
    }
}
