<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Account;
use App\Models\Tenant\AccountingEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * États comptables SYSCOHADA (2026-11-04) : balance générale, grand livre, compte de résultat
 * et bilan simplifiés, calculés à la demande depuis le journal de la mutuelle.
 *
 * Règles :
 *  - seules les écritures validées ou comptabilisées comptent (comme le budget) ; les
 *    écritures saisies non validées sont signalées, jamais additionnées ;
 *  - la date d'une écriture est celle de sa journée comptable ;
 *  - une période reste dans un même exercice (année civile) ;
 *  - les comptes de gestion (classes 6, 7, 8) repartent de zéro à chaque exercice : leurs
 *    soldes des exercices antérieurs, jamais affectés faute d'écriture de clôture, sont
 *    présentés sur une ligne calculée « Report à nouveau » (classe 1). La balance et le bilan
 *    restent ainsi équilibrés par construction (chaque écriture a un débit = un crédit).
 *
 * Montants en unités mineures de la devise de la mutuelle, jamais convertis.
 */
class AccountingStatementsService
{
    /** Ligne calculée (pas un compte du plan) : résultats antérieurs non affectés. */
    public const RAN_NUMBER = '12';
    public const RAN_LABEL = 'Report à nouveau — résultats des exercices antérieurs non affectés (calculé)';

    /** Classes dont le solde forme le résultat de l'exercice. */
    private const RESULT_CLASSES = [6, 7, 8];

    /** Exercices proposés : ceux du journal, plus l'année en cours. */
    public function years(): array
    {
        $years = DB::table('accounting_days')->selectRaw('distinct year(' . $this->g('day') . ') as y')->pluck('y')
            ->map(fn ($y) => (int) $y)->push((int) now()->year)->unique()->sortDesc()->values()->all();

        return $years;
    }

    /**
     * Totaux débit / crédit par compte entre deux dates (incluses ; null = sans borne).
     *
     * @return array<int, array{0:int,1:int}> account_id => [débit, crédit]
     */
    public function sums(?Carbon $from, ?Carbon $to, ?Carbon $cut = null): array
    {
        $base = $this->entries($from, $to, cut: $cut);
        $g = DB::connection()->getQueryGrammar();
        $debits = (clone $base)->selectRaw($g->wrap('e.debit_account_id') . ' as a, sum(' . $g->wrap('e.amount_minor') . ') as s')
            ->groupBy('e.debit_account_id')->pluck('s', 'a');
        $credits = (clone $base)->selectRaw($g->wrap('e.credit_account_id') . ' as a, sum(' . $g->wrap('e.amount_minor') . ') as s')
            ->groupBy('e.credit_account_id')->pluck('s', 'a');

        $out = [];
        foreach ($debits as $a => $s) { $out[(int) $a][0] = (int) $s; }
        foreach ($credits as $a => $s) { $out[(int) $a][1] = (int) $s; }
        foreach ($out as $a => $v) { $out[$a] = [(int) ($v[0] ?? 0), (int) ($v[1] ?? 0)]; }

        return $out;
    }

    /** Écritures saisies mais pas encore validées sur la période (exclues des états). */
    public function pendingCount(Carbon $from, Carbon $to): int
    {
        return $this->entries($from, $to, ['captured'])->count();
    }

    /**
     * Balance générale à 6 colonnes : soldes d'ouverture, mouvements de la période, soldes de clôture.
     */
    public function balance(Carbon $from, Carbon $to): array
    {
        $yearStart = $from->copy()->startOfYear();
        $beforeYear = $this->sums(null, $yearStart->copy()->subDay(), $yearStart);
        $inYearBefore = $from->gt($yearStart) ? $this->sums($yearStart, $from->copy()->subDay(), $yearStart) : [];
        $period = $this->sums($from, $to, $yearStart);

        $rows = [];
        $ran = 0;   // solde créditeur des comptes de gestion des exercices antérieurs
        foreach (Account::orderBy('number')->get() as $a) {
            if ($a->class === 9) {
                continue;   // comptabilité analytique : hors balance générale
            }
            $result = in_array($a->class, self::RESULT_CLASSES, true);
            [$bd, $bc] = $beforeYear[$a->id] ?? [0, 0];
            if ($result) {
                $ran += $bc - $bd;
                [$od, $oc] = $inYearBefore[$a->id] ?? [0, 0];
            } else {
                [$yd, $yc] = $inYearBefore[$a->id] ?? [0, 0];
                [$od, $oc] = [$bd + $yd, $bc + $yc];
            }
            [$pd, $pc] = $period[$a->id] ?? [0, 0];
            if ($od === 0 && $oc === 0 && $pd === 0 && $pc === 0) {
                continue;
            }
            $rows[] = $this->balanceRow($a->number, $a->label, $a->class, $od - $oc, $pd, $pc);
        }
        if ($ran !== 0) {
            array_unshift($rows, $this->balanceRow(self::RAN_NUMBER, self::RAN_LABEL, 1, -$ran, 0, 0, true));
        }

        $totals = ['open_d' => 0, 'open_c' => 0, 'mov_d' => 0, 'mov_c' => 0, 'close_d' => 0, 'close_c' => 0];
        foreach ($rows as $r) {
            foreach ($totals as $k => $_) { $totals[$k] += $r[$k]; }
        }

        return ['rows' => $rows, 'totals' => $totals,
            'balanced' => $totals['open_d'] === $totals['open_c'] && $totals['mov_d'] === $totals['mov_c'] && $totals['close_d'] === $totals['close_c']];
    }

    /** Grand livre d'un compte : solde d'ouverture, écritures avec contrepartie, solde progressif. */
    public function ledger(Account $account, Carbon $from, Carbon $to): array
    {
        $result = in_array($account->class, self::RESULT_CLASSES, true);
        $openFrom = $result ? $from->copy()->startOfYear() : null;
        $before = (! $result || $from->gt($from->copy()->startOfYear()))
            ? ($this->sums($openFrom, $from->copy()->subDay(), $from->copy()->startOfYear())[$account->id] ?? [0, 0]) : [0, 0];
        $opening = $before[0] - $before[1];

        $lines = AccountingEntry::query()
            ->with(['debitAccount:id,number,label', 'creditAccount:id,number,label', 'accountingDay:id,day'])
            ->whereIn('status', ['validated', 'posted'])
            ->when($this->hasKind(), fn ($q) => $q->where('kind', '!=', 'closing'))   // période dans l'exercice : avant clôture
            ->where(fn ($q) => $q->where('debit_account_id', $account->id)->orWhere('credit_account_id', $account->id))
            ->whereHas('accountingDay', fn ($q) => $q->whereBetween('day', [$from->toDateString(), $to->toDateString()]))
            ->get()
            ->sortBy(fn ($e) => [$e->accountingDay?->day?->toDateString(), $e->id])
            ->values();

        $running = $opening;
        $rows = [];
        $td = 0; $tc = 0;
        foreach ($lines as $e) {
            $isDebit = (int) $e->debit_account_id === (int) $account->id;
            $contra = $isDebit ? $e->creditAccount : $e->debitAccount;
            $d = $isDebit ? $e->amount_minor : 0;
            $c = $isDebit ? 0 : $e->amount_minor;
            $running += $d - $c;
            $td += $d; $tc += $c;
            $rows[] = ['date' => $e->accountingDay?->day, 'piece' => $e->piece, 'label' => $e->label,
                'contra' => $contra?->number, 'contra_label' => $contra?->label, 'debit' => $d, 'credit' => $c, 'balance' => $running];
        }

        return ['account' => $account, 'opening' => $opening, 'rows' => $rows, 'debit' => $td, 'credit' => $tc, 'closing' => $running];
    }

    /** Comptes ayant au moins un mouvement (pour le choix du grand livre). */
    public function accountsWithEntries(): array
    {
        $used = DB::table('accounting_entries')->whereIn('status', ['validated', 'posted'])->pluck('debit_account_id')
            ->merge(DB::table('accounting_entries')->whereIn('status', ['validated', 'posted'])->pluck('credit_account_id'))
            ->unique()->all();

        return Account::whereIn('id', $used)->orderBy('number')->get()->all();
    }

    /** Compte de résultat simplifié : charges (6), produits (7), hors activités ordinaires (8). */
    public function income(Carbon $from, Carbon $to): array
    {
        $sums = $this->sums($from, $to, $from->copy()->startOfYear());
        $out = ['charges' => [], 'produits' => [], 'hao_charges' => [], 'hao_produits' => []];
        foreach (Account::whereIn('class', self::RESULT_CLASSES)->orderBy('number')->get() as $a) {
            [$d, $c] = $sums[$a->id] ?? [0, 0];
            if ($d === 0 && $c === 0) {
                continue;
            }
            $net = $d - $c;   // > 0 : charge nette
            $row = ['number' => $a->number, 'label' => $a->label];
            match (true) {
                $a->class === 6 => $out['charges'][] = $row + ['amount' => $net],
                $a->class === 7 => $out['produits'][] = $row + ['amount' => -$net],
                $net >= 0       => $out['hao_charges'][] = $row + ['amount' => $net],
                default         => $out['hao_produits'][] = $row + ['amount' => -$net],
            };
        }
        $sum = fn ($k) => array_sum(array_column($out[$k], 'amount'));
        $out['total_charges'] = $sum('charges');
        $out['total_produits'] = $sum('produits');
        $out['resultat_ordinaire'] = $out['total_produits'] - $out['total_charges'];
        $out['resultat_hao'] = $sum('hao_produits') - $sum('hao_charges');
        $out['resultat'] = $out['resultat_ordinaire'] + $out['resultat_hao'];

        return $out;
    }

    /**
     * Bilan simplifié à une date : actif (immobilisations, stocks, créances, trésorerie-actif),
     * passif (capitaux propres dont report à nouveau et résultat de l'exercice, dettes,
     * trésorerie-passif). Un compte de tiers ou de trésorerie va à l'actif ou au passif selon
     * le sens de son solde.
     */
    public function balanceSheet(Carbon $at): array
    {
        $yearStart = $at->copy()->startOfYear();
        $cumul = $this->sums(null, $at, $yearStart);
        $year = $this->sums($yearStart, $at, $yearStart);

        $sections = [
            'actif'  => ['immobilisations' => [], 'stocks' => [], 'creances' => [], 'tresorerie' => []],
            'passif' => ['capitaux' => [], 'dettes' => [], 'tresorerie' => []],
        ];
        $ranPrior = 0;
        $resultYear = 0;
        foreach (Account::orderBy('number')->get() as $a) {
            [$d, $c] = $cumul[$a->id] ?? [0, 0];
            if (in_array($a->class, self::RESULT_CLASSES, true)) {
                [$yd, $yc] = $year[$a->id] ?? [0, 0];
                $resultYear += $yc - $yd;
                $ranPrior += ($c - $yc) - ($d - $yd);
                continue;
            }
            $solde = $d - $c;   // > 0 : débiteur
            if ($solde === 0 || $a->class === 9) {
                continue;
            }
            $row = ['number' => $a->number, 'label' => $a->label];
            match (true) {
                $a->class === 1 => $sections['passif']['capitaux'][] = $row + ['amount' => -$solde],
                $a->class === 2 => $sections['actif']['immobilisations'][] = $row + ['amount' => $solde],
                $a->class === 3 => $sections['actif']['stocks'][] = $row + ['amount' => $solde],
                $a->class === 4 => $solde > 0 ? $sections['actif']['creances'][] = $row + ['amount' => $solde]
                                              : $sections['passif']['dettes'][] = $row + ['amount' => -$solde],
                default         => $solde > 0 ? $sections['actif']['tresorerie'][] = $row + ['amount' => $solde]
                                              : $sections['passif']['tresorerie'][] = $row + ['amount' => -$solde],
            };
        }
        if ($ranPrior !== 0) {
            $sections['passif']['capitaux'][] = ['number' => self::RAN_NUMBER, 'label' => self::RAN_LABEL, 'amount' => $ranPrior, 'computed' => true];
        }
        $sections['passif']['capitaux'][] = ['number' => '13', 'label' => 'Résultat de l’exercice ' . $at->year . ' au ' . $at->format('d/m/Y') . ' (calculé)', 'amount' => $resultYear, 'computed' => true];

        $total = fn (array $groups) => array_sum(array_map(fn ($rows) => array_sum(array_column($rows, 'amount')), $groups));
        $actif = $total($sections['actif']);
        $passif = $total($sections['passif']);

        return $sections + ['total_actif' => $actif, 'total_passif' => $passif, 'result' => $resultYear, 'balanced' => $actif === $passif];
    }

    private function balanceRow(string $number, string $label, int $class, int $openingSolde, int $pd, int $pc, bool $computed = false): array
    {
        $close = $openingSolde + $pd - $pc;

        return [
            'number' => $number, 'label' => $label, 'class' => $class, 'computed' => $computed,
            'open_d' => max($openingSolde, 0), 'open_c' => max(-$openingSolde, 0),
            'mov_d' => $pd, 'mov_c' => $pc,
            'close_d' => max($close, 0), 'close_c' => max(-$close, 0),
        ];
    }

    /** Exercice clôturé (null si ouvert) : les états sont présentés avant écritures de clôture. */
    public function closedYear(int $year): ?\App\Models\Tenant\FiscalYear
    {
        return \App\Models\Tenant\FiscalYear::where('label', (string) $year)->where('status', 'closed')->first();
    }

    /**
     * Écritures retenues, jointes à leur journée comptable (date).
     * Écritures de clôture (2026-11-05) : prises en compte seulement avant $cut (début de
     * l'exercice présenté). Les états d'un exercice se lisent donc AVANT sa propre clôture,
     * et les exercices suivants repartent des comptes de résultat 131 / 139.
     */
    private function entries(?Carbon $from, ?Carbon $to, array $statuses = ['validated', 'posted'], ?Carbon $cut = null)
    {
        return DB::table('accounting_entries as e')
            ->join('accounting_days as d', 'd.id', '=', 'e.accounting_day_id')
            ->whereIn('e.status', $statuses)
            ->when($this->hasKind(), fn ($q) => $cut
                ? $q->where(fn ($w) => $w->where('e.kind', '!=', 'closing')->orWhere('d.day', '<', $cut->toDateString()))
                : $q)
            ->when($from, fn ($q) => $q->where('d.day', '>=', $from->toDateString()))
            ->when($to, fn ($q) => $q->where('d.day', '<=', $to->toDateString()));
    }

    private ?bool $hasKind = null;

    /** Colonne « kind » présente (migration du 2026-11-05 appliquée) ? */
    private function hasKind(): bool
    {
        return $this->hasKind ??= \Illuminate\Support\Facades\Schema::hasColumn('accounting_entries', 'kind');
    }

    private function g(string $column): string
    {
        return DB::connection()->getQueryGrammar()->wrap($column);
    }
}
