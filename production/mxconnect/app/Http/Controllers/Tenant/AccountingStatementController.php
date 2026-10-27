<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Concerns\MutualContext;
use App\Models\Tenant\Account;
use App\Services\Tenant\AccountingStatementsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * États comptables SYSCOHADA (2026-11-04) : balance générale, grand livre, compte de résultat,
 * bilan. Lecture seule, imprimables, exportables en CSV. Une période = un exercice (année
 * civile), ou des dates choisies à l'intérieur de cet exercice.
 */
class AccountingStatementController extends Controller
{
    use MutualContext;

    public const STATEMENTS = [
        'balance'  => ['accounting.balance', 'Balance générale'],
        'ledger'   => ['accounting.ledger', 'Grand livre'],
        'income'   => ['accounting.income', 'Compte de résultat'],
        'sheet'    => ['accounting.sheet', 'Bilan'],
    ];

    public function __construct(private AccountingStatementsService $statements) {}

    public function balance(Request $request)
    {
        $p = $this->period($request);

        return $this->view('balance', $p, ['balance' => $this->statements->balance($p['from'], $p['to'])]);
    }

    public function ledger(Request $request)
    {
        $p = $this->period($request);
        $accounts = $this->statements->accountsWithEntries();
        $account = $request->filled('compte')
            ? Account::where('number', (string) $request->get('compte'))->first()
            : ($accounts[0] ?? null);

        return $this->view('ledger', $p, [
            'accounts' => $accounts,
            'ledger'   => $account ? $this->statements->ledger($account, $p['from'], $p['to']) : null,
        ]);
    }

    public function income(Request $request)
    {
        $p = $this->period($request);

        return $this->view('income', $p, ['income' => $this->statements->income($p['from'], $p['to'])]);
    }

    public function sheet(Request $request)
    {
        $p = $this->period($request);

        return $this->view('sheet', $p, ['sheet' => $this->statements->balanceSheet($p['to'])]);
    }

    /** Export CSV (tableur) de l'état demandé, sur la période affichée. */
    public function export(Request $request)
    {
        $etat = (string) $request->get('etat');
        abort_unless(array_key_exists($etat, self::STATEMENTS), 404);
        $p = $this->period($request);
        $c = $this->currency();
        $m = fn (int $minor) => number_format($minor / (10 ** $c->minor_unit), $c->minor_unit, ',', '');
        $rows = [[self::STATEMENTS[$etat][1], tenant('name')],
            [$etat === 'sheet' ? 'Au' : 'Période', $etat === 'sheet' ? $p['to']->format('d/m/Y') : $p['from']->format('d/m/Y') . ' au ' . $p['to']->format('d/m/Y')],
            ['Devise', $c->code], []];

        if ($etat === 'balance') {
            $b = $this->statements->balance($p['from'], $p['to']);
            $rows[] = ['Compte', 'Intitulé', 'Ouverture débit', 'Ouverture crédit', 'Mouvements débit', 'Mouvements crédit', 'Solde débiteur', 'Solde créditeur'];
            foreach ([...$b['rows'], ['number' => 'TOTAL', 'label' => ''] + $b['totals']] as $r) {
                $rows[] = [$r['number'], $r['label'], $m($r['open_d']), $m($r['open_c']), $m($r['mov_d']), $m($r['mov_c']), $m($r['close_d']), $m($r['close_c'])];
            }
        } elseif ($etat === 'ledger') {
            $accounts = $request->get('compte') === 'tous' || ! $request->filled('compte')
                ? $this->statements->accountsWithEntries()
                : array_filter([Account::where('number', (string) $request->get('compte'))->first()]);
            $rows[] = ['Compte', 'Date', 'Pièce', 'Libellé', 'Contrepartie', 'Débit', 'Crédit', 'Solde'];
            foreach ($accounts as $a) {
                $l = $this->statements->ledger($a, $p['from'], $p['to']);
                $rows[] = [$a->number . ' ' . $a->label, '', '', 'Solde d’ouverture', '', '', '', $m($l['opening'])];
                foreach ($l['rows'] as $r) {
                    $rows[] = [$a->number, $r['date']?->format('d/m/Y'), $r['piece'], $r['label'], $r['contra'], $m($r['debit']), $m($r['credit']), $m($r['balance'])];
                }
                $rows[] = [$a->number, '', '', 'Total et solde de clôture', '', $m($l['debit']), $m($l['credit']), $m($l['closing'])];
            }
        } elseif ($etat === 'income') {
            $i = $this->statements->income($p['from'], $p['to']);
            $rows[] = ['Compte', 'Intitulé', 'Montant'];
            $rows[] = ['', 'CHARGES (classe 6)', ''];
            foreach ($i['charges'] as $r) { $rows[] = [$r['number'], $r['label'], $m($r['amount'])]; }
            $rows[] = ['', 'Total des charges', $m($i['total_charges'])];
            $rows[] = ['', 'PRODUITS (classe 7)', ''];
            foreach ($i['produits'] as $r) { $rows[] = [$r['number'], $r['label'], $m($r['amount'])]; }
            $rows[] = ['', 'Total des produits', $m($i['total_produits'])];
            $rows[] = ['', 'Résultat des activités ordinaires', $m($i['resultat_ordinaire'])];
            foreach ([...$i['hao_produits'], ...array_map(fn ($r) => ['amount' => -$r['amount']] + $r, $i['hao_charges'])] as $r) { $rows[] = [$r['number'], $r['label'] . ' (HAO)', $m($r['amount'])]; }
            $rows[] = ['', 'Résultat hors activités ordinaires', $m($i['resultat_hao'])];
            $rows[] = ['', $i['resultat'] >= 0 ? 'RÉSULTAT NET (excédent)' : 'RÉSULTAT NET (déficit)', $m($i['resultat'])];
        } else {
            $s = $this->statements->balanceSheet($p['to']);
            $rows[] = ['Rubrique', 'Compte', 'Intitulé', 'Montant'];
            foreach (['actif' => self::SHEET_GROUPS['actif'], 'passif' => self::SHEET_GROUPS['passif']] as $side => $groups) {
                $rows[] = [strtoupper($side), '', '', ''];
                foreach ($groups as $k => $label) {
                    foreach ($s[$side][$k] as $r) { $rows[] = [$label, $r['number'], $r['label'], $m($r['amount'])]; }
                }
                $rows[] = ['Total ' . $side, '', '', $m($s['total_' . $side])];
            }
        }

        $out = fopen('php://temp', 'w+');
        fwrite($out, "\xEF\xBB\xBF");   // BOM : accents corrects dans Excel
        foreach ($rows as $row) { fputcsv($out, $row, ';'); }
        rewind($out);
        $slug = ['balance' => 'balance', 'ledger' => 'grand-livre', 'income' => 'compte-de-resultat', 'sheet' => 'bilan'][$etat];

        return response(stream_get_contents($out), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $slug . '-' . $p['from']->format('Ymd') . '-' . $p['to']->format('Ymd') . '.csv"',
        ])->setPrivate();
    }

    public const SHEET_GROUPS = [
        'actif'  => ['immobilisations' => 'Actif immobilisé (classe 2)', 'stocks' => 'Stocks (classe 3)', 'creances' => 'Créances (classe 4)', 'tresorerie' => 'Trésorerie-actif (classe 5)'],
        'passif' => ['capitaux' => 'Capitaux propres et ressources assimilées (classe 1)', 'dettes' => 'Dettes (classe 4)', 'tresorerie' => 'Trésorerie-passif (classe 5)'],
    ];

    private function view(string $etat, array $p, array $data)
    {
        return view('tenant.accounting.statements', $p + $data + [
            'etat'     => $etat,
            'currency' => $this->currency(),
            'years'    => $this->statements->years(),
            'pending'  => $this->statements->pendingCount($p['from'], $p['to']),
        ]);
    }

    /** Exercice (année civile) et, en option, dates choisies à l'intérieur de cet exercice. */
    private function period(Request $request): array
    {
        $year = (int) $request->integer('exercice', (int) now()->year);
        $year = $year >= 2000 && $year <= 2100 ? $year : (int) now()->year;
        $start = Carbon::create($year, 1, 1)->startOfDay();
        $end = $year === (int) now()->year ? now()->startOfDay() : Carbon::create($year, 12, 31)->startOfDay();

        if ($request->filled('du') || $request->filled('au')) {
            // En cas d'erreur, retour à l'écran de l'état (et non à « l'URL précédente », qui peut
            // être un téléchargement CSV : le navigateur téléchargerait au lieu d'afficher l'erreur).
            $screen = self::STATEMENTS[$request->get('etat')][0] ?? $request->route()->getName();
            $screen = str_ends_with($screen, '.export') ? 'accounting.balance' : $screen;
            $this->validateOr(route($screen, ['exercice' => $year] + $request->only('compte')), $request, [
                'du' => ['nullable', 'date', 'after_or_equal:' . $start->toDateString(), 'before_or_equal:' . $year . '-12-31'],
                'au' => ['nullable', 'date', ...($request->filled('du') ? ['after_or_equal:du'] : []), 'after_or_equal:' . $start->toDateString(), 'before_or_equal:' . $year . '-12-31'],
            ], [
                'du.after_or_equal' => 'Les dates doivent rester dans l’exercice ' . $year . '.', 'du.before_or_equal' => 'Les dates doivent rester dans l’exercice ' . $year . '.',
                'au.before_or_equal' => 'Les dates doivent rester dans l’exercice ' . $year . '.', 'au.after_or_equal' => 'La fin doit suivre le début, dans l’exercice ' . $year . '.',
            ]);
            $from = $request->filled('du') ? Carbon::parse($request->get('du'))->startOfDay() : $start;
            $to = $request->filled('au') ? Carbon::parse($request->get('au'))->startOfDay() : Carbon::create($year, 12, 31)->startOfDay();
        } else {
            [$from, $to] = [$start, $end];
        }

        return ['year' => $year, 'from' => $from, 'to' => $to];
    }
}
