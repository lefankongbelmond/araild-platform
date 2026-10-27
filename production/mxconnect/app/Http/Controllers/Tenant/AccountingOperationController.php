<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Concerns\MutualContext;
use App\Models\Tenant\Account;
use App\Models\Tenant\AccountingEntry;
use App\Services\AccountingRefusal;
use App\Services\AccountingService;
use App\Support\SyscohadaAccounts;
use App\Support\TenantAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Opérations diverses et plan comptable (2026-11-05).
 *  - le trésorier-comptable saisit une écriture (débit, crédit, montant, libellé) ;
 *  - l'administrateur ou le contrôleur la valide ou la rejette (jamais la personne qui l'a
 *    saisie) ; seules les écritures validées entrent dans les états ;
 *  - une écriture validée se corrige par une extourne (contre-passation), elle aussi validée
 *    par une autre personne ; rien n'est jamais modifié ni supprimé en place.
 */
class AccountingOperationController extends Controller
{
    use MutualContext;

    public function __construct(private AccountingService $accounting) {}

    public function index(Request $request)
    {
        $recent = AccountingEntry::with(['debitAccount', 'creditAccount', 'accountingDay', 'capturedBy', 'validatedBy'])
            ->whereIn('kind', ['manual', 'reversal'])->where('status', '!=', 'captured')->latest('id')->limit(30)->get();

        return view('tenant.accounting.operations', [
            'pending'  => AccountingEntry::with(['debitAccount', 'creditAccount', 'accountingDay', 'capturedBy'])->where('status', 'captured')->orderBy('id')->get(),
            'recent'   => $recent,
            'reversed' => AccountingEntry::where('kind', 'reversal')->whereIn('status', ['captured', 'validated', 'posted'])
                ->whereIn('reversed_entry_id', $recent->pluck('id'))->pluck('reversed_entry_id')->all(),
            'accounts' => Account::where('class', '<=', 8)->orderBy('number')->get(),
            'currency' => $this->currency(),
            'canCapture'  => TenantAccess::can('accounting.entries.store'),
            'canDecide'   => TenantAccess::can('accounting.entries.validate'),
            'canReverse'  => TenantAccess::can('accounting.entries.reverse'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date'      => ['required', 'date'],
            'debit'     => ['required', 'integer', Rule::exists('accounts', 'id')],
            'credit'    => ['required', 'integer', Rule::exists('accounts', 'id'), 'different:debit'],
            'amount'    => ['required', 'string', 'max:24'],
            'label'     => ['required', 'string', 'min:3', 'max:255'],
            'piece'     => ['nullable', 'string', 'max:60'],
        ], ['credit.different' => 'Le compte débité et le compte crédité doivent être différents.', 'label.required' => 'Indiquez le libellé de l’opération.']);
        $amount = $this->currency()->toMinor($this->amount($data['amount']));

        return $this->attempt(function () use ($data, $amount, $request) {
            $e = $this->accounting->capture($data['date'], Account::findOrFail($data['debit']), Account::findOrFail($data['credit']),
                $amount, trim($data['label']), ! empty($data['piece']) ? trim($data['piece']) : null, (int) $request->user()->id);

            return redirect()->route('accounting.entries.index')
                ->with('success', "Opération enregistrée (pièce {$e->piece}). Elle attend la validation d’une autre personne (administrateur ou contrôleur).");
        });
    }

    public function validateEntry(Request $request, AccountingEntry $entry)
    {
        return $this->attempt(function () use ($request, $entry) {
            $this->accounting->validateEntry($entry, (int) $request->user()->id);

            return back()->with('success', "Écriture {$entry->piece} validée : elle figure désormais dans le journal et les états.");
        });
    }

    public function reject(Request $request, AccountingEntry $entry)
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:5', 'max:500']],
            ['note.required' => 'Indiquez le motif du rejet.', 'note.min' => 'Précisez le motif du rejet.']);

        return $this->attempt(function () use ($request, $entry, $data) {
            $this->accounting->rejectEntry($entry, (int) $request->user()->id, trim($data['note']));

            return back()->with('success', "Écriture {$entry->piece} rejetée. Elle n’entre pas dans les états.");
        });
    }

    public function reverse(Request $request, AccountingEntry $entry)
    {
        $data = $request->validate(['date' => ['required', 'date']]);

        return $this->attempt(function () use ($request, $entry, $data) {
            $r = $this->accounting->reverse($entry, $data['date'], (int) $request->user()->id);

            return back()->with('success', "Extourne {$r->piece} préparée. Elle attend la validation d’une autre personne.");
        });
    }

    // --- Plan comptable ---

    public function storeAccount(Request $request)
    {
        $data = $request->validate([
            'number' => ['required', 'regex:/^[1-8][0-9]{1,9}$/', Rule::unique('accounts', 'number')],
            'label'  => ['required', 'string', 'min:2', 'max:180'],
        ], ['number.regex' => 'Numéro SYSCOHADA : 2 à 10 chiffres, commençant par la classe (1 à 8).', 'number.unique' => 'Ce numéro de compte existe déjà.']);
        Account::create(['number' => $data['number'], 'label' => trim($data['label']), 'class' => (int) $data['number'][0]]);

        return back()->with('success', "Compte {$data['number']} ajouté au plan comptable.");
    }

    public function importAccounts()
    {
        $added = 0;
        foreach (SyscohadaAccounts::USUAL as $number => $label) {
            $a = Account::firstOrCreate(['number' => (string) $number], ['label' => $label, 'class' => (int) ((string) $number)[0]]);
            $added += $a->wasRecentlyCreated ? 1 : 0;
        }

        return back()->with('success', $added ? "{$added} compte(s) usuel(s) ajouté(s). Les comptes existants n’ont pas été modifiés." : 'Tous les comptes usuels sont déjà dans votre plan.');
    }

    public function updateAccount(Request $request, Account $account)
    {
        $data = $request->validate(['label' => ['required', 'string', 'min:2', 'max:180']]);
        $account->update(['label' => trim($data['label'])]);

        return back()->with('success', "Compte {$account->number} renommé.");
    }

    public function destroyAccount(Account $account)
    {
        if ($reason = $this->protectedReason($account)) {
            return back()->with('error', "Le compte {$account->number} ne peut pas être supprimé : {$reason}.");
        }
        $account->delete();

        return back()->with('success', "Compte {$account->number} supprimé du plan comptable.");
    }

    /** Pourquoi un compte ne peut pas être supprimé (null = suppression possible). */
    public static function protectedReason(Account $account): ?string
    {
        $cfg = config('mxconnect.accounting');
        $system = array_merge(array_keys($cfg['chart'] ?? []), array_values($cfg['mode_account'] ?? []),
            array_values($cfg['source_income'] ?? []), array_values($cfg['source_expense'] ?? []), array_keys(AccountingService::CLOSING_ACCOUNTS));

        return match (true) {
            in_array($account->number, array_map('strval', $system), true) => 'il est utilisé par la comptabilisation automatique ou la clôture',
            AccountingEntry::where('debit_account_id', $account->id)->orWhere('credit_account_id', $account->id)->exists() => 'il a des écritures',
            \Illuminate\Support\Facades\Schema::hasTable('budget_lines') && \App\Models\Tenant\BudgetLine::where('account_id', $account->id)->exists() => 'il est utilisé dans un budget',
            default => null,
        };
    }

    /** Exécute une opération ; un refus métier revient à l'écran avec son message. */
    private function attempt(\Closure $action)
    {
        try {
            return $action();
        } catch (AccountingRefusal $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }
}
