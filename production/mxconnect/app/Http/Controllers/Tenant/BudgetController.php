<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Concerns\MutualContext;
use App\Models\Tenant\Account;
use App\Models\Tenant\Budget;
use App\Models\Tenant\BudgetLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Gestion budgétaire : budget annuel (recettes / dépenses) par compte du plan
 * comptable, réalisé calculé depuis les écritures comptables validées, taux
 * d'exécution. Un budget adopté (par l'AG ou le conseil) n'est plus modifiable.
 */
class BudgetController extends Controller
{
    use MutualContext;

    // Droits : matrice App\Support\TenantAccess (lecture : admin, contrôleur, trésorier, auditeur ; saisie : trésorier ; adoption : admin).

    public function index(Request $request)
    {
        \App\Support\TenantAccess::authorize();
        $year = max(2000, min(2100, (int) $request->get('annee', now()->year)));
        $budget = Budget::with(['lines.account'])->where('year', $year)->first();
        $realized = $this->realized($year);
        $prevRealized = $this->realized($year - 1);

        $rows = ['income' => [], 'expense' => []];
        foreach ($budget?->lines ?? [] as $l) {
            $done = $l->account_id ? ($realized[$l->account_id] ?? 0) : null;
            $rows[$l->kind][] = ['line' => $l, 'realized' => $done,
                'rate' => ($done !== null && $l->amount_minor > 0) ? round($done * 100 / $l->amount_minor, 1) : null];
        }
        $sum = fn ($k, $f) => array_sum(array_map(fn ($r) => $f === 'b' ? $r['line']->amount_minor : (int) ($r['realized'] ?? 0), $rows[$k]));

        return view('tenant.budget.index', [
            'year' => $year, 'budget' => $budget, 'rows' => $rows, 'currency' => $this->currency(),
            'accounts' => Account::whereIn('class', [6, 7])->orderBy('number')->get(),
            'totals' => [
                'income_b' => $sum('income', 'b'), 'income_r' => $sum('income', 'r'),
                'expense_b' => $sum('expense', 'b'), 'expense_r' => $sum('expense', 'r'),
            ],
            'prevRealized' => $prevRealized,
            'years' => Budget::orderByDesc('year')->pluck('year'),
        ]);
    }

    public function create(Request $request)
    {
        \App\Support\TenantAccess::authorize();
        $data = $request->validate([
            'year'    => ['required', 'integer', 'min:2000', 'max:2100', Rule::unique('budgets', 'year')],
            'prefill' => ['nullable', 'in:accounts,previous'],
        ]);
        DB::transaction(function () use ($data) {
            $b = Budget::create(['year' => $data['year'], 'label' => 'Budget ' . $data['year'], 'status' => 'draft']);
            if (! empty($data['prefill'])) {
                $prev = $data['prefill'] === 'previous' ? $this->realized($data['year'] - 1) : [];
                foreach (Account::whereIn('class', [6, 7])->orderBy('number')->get() as $a) {
                    $b->lines()->create([
                        'kind' => $a->class === 7 ? 'income' : 'expense', 'account_id' => $a->id,
                        'label' => $a->label, 'amount_minor' => max(0, (int) ($prev[$a->id] ?? 0)),
                    ]);
                }
            }
        });

        return redirect()->route('budget.index', ['annee' => $data['year']])->with('success', 'Budget ' . $data['year'] . ' créé (brouillon).');
    }

    public function storeLine(Request $request, Budget $budget)
    {
        \App\Support\TenantAccess::authorize();
        abort_if($budget->isAdopted(), 422, 'Budget adopté : il n’est plus modifiable.');
        $data = $request->validate([
            'kind'       => ['required', 'in:income,expense'],
            'account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id'), Rule::unique('budget_lines', 'account_id')->where('budget_id', $budget->id)],
            'label'      => ['required_without:account_id', 'nullable', 'string', 'max:180'],
            'amount'     => ['required', 'string', 'max:20'],
        ], ['account_id.unique' => 'Ce compte a déjà une ligne dans ce budget.', 'label.required_without' => 'Indiquez un libellé ou choisissez un compte.']);
        $account = ! empty($data['account_id']) ? Account::find($data['account_id']) : null;
        $budget->lines()->create([
            'kind' => $account ? ($account->class === 7 ? 'income' : 'expense') : $data['kind'],
            'account_id' => $account?->id,
            'label' => $data['label'] ?: $account?->label,
            'amount_minor' => max(0, $this->currency()->toMinor($this->amount($data['amount']))),
        ]);

        return back()->with('success', 'Ligne budgétaire ajoutée.');
    }

    public function updateLines(Request $request, Budget $budget)
    {
        \App\Support\TenantAccess::authorize();
        abort_if($budget->isAdopted(), 422, 'Budget adopté : il n’est plus modifiable.');
        $currency = $this->currency();
        foreach ((array) $request->input('amounts', []) as $id => $raw) {
            $line = $budget->lines()->find((int) $id);
            if ($line && $raw !== null && $raw !== '') {
                $line->update(['amount_minor' => max(0, $currency->toMinor($this->amount($raw)))]);
            }
        }
        if ($request->filled('notes') || $request->has('notes')) {
            $budget->update(['notes' => mb_substr((string) $request->input('notes'), 0, 2000) ?: null]);
        }

        return back()->with('success', 'Montants enregistrés.');
    }

    public function destroyLine(Request $request, Budget $budget, BudgetLine $line)
    {
        \App\Support\TenantAccess::authorize();
        abort_if($budget->isAdopted() || $line->budget_id !== $budget->id, 422);
        $line->delete();

        return back()->with('success', 'Ligne supprimée.');
    }

    public function adopt(Request $request, Budget $budget)
    {
        abort_unless($request->user()?->hasRole('mutual_admin'), 403, 'Seul l’administrateur adopte le budget.');
        abort_if($budget->isAdopted(), 422);
        abort_if($budget->lines()->count() === 0, 422, 'Le budget est vide.');
        $request->validate(['adopted_on' => ['required', 'date']], [], ['adopted_on' => 'date d’adoption']);
        $budget->update(['status' => 'adopted', 'adopted_on' => $request->input('adopted_on'), 'adopted_by' => $request->user()->id]);

        return back()->with('success', 'Budget adopté. Il n’est plus modifiable ; son exécution reste suivie automatiquement.');
    }

    /**
     * Réalisé par compte (unités mineures) pour une année, depuis les écritures
     * validées ou comptabilisées (hors écritures d'annulation).
     * Classe 7 (produits) : crédits − débits ; classe 6 (charges) : débits − crédits.
     *
     * @return array<int,int>
     */
    private function realized(int $year): array
    {
        $base = DB::table('accounting_entries as e')
            ->join('accounting_days as d', 'd.id', '=', 'e.accounting_day_id')
            ->whereYear('d.day', $year)
            ->whereIn('e.status', ['validated', 'posted']);
        $debits = (clone $base)->selectRaw('e.debit_account_id as a, sum(e.amount_minor) as s')->groupBy('e.debit_account_id')->pluck('s', 'a');
        $credits = (clone $base)->selectRaw('e.credit_account_id as a, sum(e.amount_minor) as s')->groupBy('e.credit_account_id')->pluck('s', 'a');
        $out = [];
        foreach (Account::whereIn('class', [6, 7])->get(['id', 'class']) as $a) {
            $d = (int) ($debits[$a->id] ?? 0); $c = (int) ($credits[$a->id] ?? 0);
            $out[$a->id] = $a->class === 7 ? $c - $d : $d - $c;
        }

        return $out;
    }
}
