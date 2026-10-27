<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Concerns\MutualContext;
use App\Models\Tenant\Account;
use App\Models\Tenant\AccountingEntry;
use App\Models\Tenant\FiscalYear;
use App\Models\Tenant\User;
use App\Services\AccountingRefusal;
use App\Services\AccountingService;
use App\Support\TenantAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Clôture d'exercice (2026-11-05) : une fois l'année terminée et toutes ses écritures
 * validées, le trésorier-comptable (ou l'administrateur) clôture : les comptes de gestion
 * sont soldés dans le résultat (131 bénéfice / 139 perte) et l'année est verrouillée.
 * L'administrateur peut rouvrir, avec un motif, tant que le résultat n'est pas affecté.
 */
class FiscalClosingController extends Controller
{
    use MutualContext;

    public function __construct(private AccountingService $accounting) {}

    public function index()
    {
        $years = DB::table('accounting_days')->selectRaw('distinct year(' . DB::connection()->getQueryGrammar()->wrap('day') . ') as y')->pluck('y')
            ->map(fn ($y) => (int) $y)->merge(FiscalYear::pluck('label')->map(fn ($l) => (int) $l))->push((int) now()->year)
            ->filter(fn ($y) => $y > 1999)->unique()->sortDesc()->values();
        $fys = FiscalYear::whereIn('label', $years->map(fn ($y) => (string) $y))->get()->keyBy('label');
        $users = User::whereIn('id', $fys->pluck('closed_by')->merge($fys->flatMap(fn ($f) => collect($f->history ?? [])->pluck('by')))->filter()->unique())
            ->get()->mapWithKeys(fn ($u) => [$u->id => $u->name]);
        $ids = Account::whereIn('number', ['121', '129', '131', '139'])->pluck('id', 'number');

        return view('tenant.accounting.closing', [
            'rows' => $years->map(fn ($y) => [
                'year'     => $y,
                'fy'       => $fys[(string) $y] ?? null,
                'blockers' => ($fys[(string) $y] ?? null)?->isClosed() ? [] : $this->accounting->closingBlockers($y),
                'entries'  => AccountingEntry::whereIn('status', ['validated', 'posted'])->where('kind', '!=', 'closing')
                    ->whereHas('accountingDay', fn ($q) => $q->whereYear('day', $y))->count(),
            ])->all(),
            'users'    => $users,
            'ids'      => $ids,
            'currency' => $this->currency(),
            'canClose'  => TenantAccess::can('accounting.closing.close'),
            'canReopen' => TenantAccess::can('accounting.closing.reopen'),
            'canCapture' => TenantAccess::can('accounting.entries.store'),
        ]);
    }

    public function close(Request $request, int $year)
    {
        try {
            $result = $this->accounting->closeYear($year, (int) $request->user()->id);
        } catch (AccountingRefusal $e) {
            return back()->with('error', $e->getMessage());
        }
        $c = $this->currency();

        return back()->with('success', "Exercice {$year} clôturé. " . ($result >= 0 ? 'Bénéfice' : 'Perte') . ' : ' . $c->format(abs($result))
            . '. Plus aucune écriture ne peut être datée de ' . $year . '. Il reste à affecter le résultat (décision de l’assemblée générale).');
    }

    public function reopen(Request $request, int $year)
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:5', 'max:500']],
            ['note.required' => 'Indiquez le motif de la réouverture.', 'note.min' => 'Précisez le motif de la réouverture.']);
        try {
            $this->accounting->reopenYear($year, (int) $request->user()->id, trim($data['note']));
        } catch (AccountingRefusal $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Exercice {$year} rouvert : les écritures de clôture ont été retirées. Pensez à le clôturer à nouveau.");
    }
}
