<?php

/*
 * Lot 12 — plan comptable modifiable, opérations diverses (saisie ≠ validation), extournes,
 * clôture d'exercice. Testé dans une VRAIE base de mutuelle (base dédiée, ou tables
 * préfixées avec MX_TEST_STORAGE=prefix).
 */

use App\Models\Tenant\Account;
use App\Models\Tenant\AccountingEntry;
use App\Models\Tenant\FiscalYear;
use App\Services\AccountingRefusal;
use App\Services\AccountingService;
use App\Services\Tenant\AccountingStatementsService;
use App\Support\SyscohadaAccounts;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-27 10:00:00'));
    provisionTestMutual();
    staffUser('tres@equipe.test', ['treasurer_accountant']);
    staffUser('ctrl@equipe.test', ['controller_validator']);
    staffUser('gest@equipe.test', ['benefits_manager']);
    $this->ids = $this->mutual->run(fn () => Account::pluck('id', 'number')->all());
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

/** Écritures validées 2025 : cotisations 50 000, remboursement 20 000 → bénéfice 30 000. */
function seed2025(): void
{
    test()->mutual->run(function () {
        $acc = app(AccountingService::class);
        $acc->post('2025-06-10', '571', '7561', 50000, 'Cotisations 2025', 'P2025-1');
        $acc->post('2025-06-12', '6011', '571', 20000, 'Remboursement 2025', 'P2025-2');
    });
}

function balanceRows(string $from, string $to): array
{
    return test()->mutual->run(fn () => app(AccountingStatementsService::class)->balance(Carbon::parse($from), Carbon::parse($to)));
}

it('saisit une opération diverse, validée seulement par une autre personne', function () {
    $h = $this->host;
    $od = ['date' => '2026-03-15', 'debit' => $this->ids['6580'], 'credit' => $this->ids['521'], 'amount' => '12 500', 'label' => 'Fournitures de bureau', 'piece' => 'FACT-14'];

    as_('tres@equipe.test')->post($h . '/comptabilite/operations', ['credit' => $this->ids['6580']] + $od)->assertSessionHasErrors('credit');
    as_('tres@equipe.test')->post($h . '/comptabilite/operations', $od)->assertRedirect()->assertSessionHas('success');
    as_('ctrl@equipe.test')->post($h . '/comptabilite/operations', $od)->assertForbidden();   // saisie réservée au trésorier
    $e = $this->mutual->run(fn () => AccountingEntry::where('piece', 'FACT-14')->firstOrFail());
    expect($e->status)->toBe('captured')->and($e->kind)->toBe('manual')->and($e->amount_minor)->toBe(12500);

    // Non validée : hors des états, signalée.
    expect(collect(balanceRows('2026-01-01', '2026-10-27')['rows'])->pluck('number'))->not->toContain('6580');
    as_('tres@equipe.test')->get($h . '/comptabilite/balance')->assertSee('1 écriture(s) de la période attendent encore leur validation', false);

    // 4 yeux, au niveau du service (même si une personne cumulait les rôles).
    expect(fn () => $this->mutual->run(fn () => app(AccountingService::class)->validateEntry($e->fresh(), (int) $e->captured_by)))
        ->toThrow(AccountingRefusal::class, 'une autre personne');
    as_('tres@equipe.test')->post($h . "/comptabilite/ecritures/{$e->id}/validation")->assertForbidden();

    as_('ctrl@equipe.test')->get($h . '/comptabilite/operations')->assertOk()->assertSee('Fournitures de bureau')->assertSee('Valider');
    as_('ctrl@equipe.test')->post($h . "/comptabilite/ecritures/{$e->id}/validation")->assertSessionHas('success');
    expect($this->mutual->run(fn () => $e->fresh()->status))->toBe('validated');
    expect(collect(balanceRows('2026-01-01', '2026-10-27')['rows'])->keyBy('number')['6580'])->toMatchArray(['close_d' => 12500]);
    as_('ctrl@equipe.test')->post($h . "/comptabilite/ecritures/{$e->id}/validation")->assertSessionHas('error');   // déjà décidée

    // Rejet motivé.
    as_('tres@equipe.test')->post($h . '/comptabilite/operations', ['piece' => 'FACT-15'] + $od);
    $r = $this->mutual->run(fn () => AccountingEntry::where('piece', 'FACT-15')->firstOrFail());
    as_('ctrl@equipe.test')->post($h . "/comptabilite/ecritures/{$r->id}/rejet", ['note' => ''])->assertSessionHasErrors('note');
    as_('ctrl@equipe.test')->post($h . "/comptabilite/ecritures/{$r->id}/rejet", ['note' => 'Facture en double'])->assertSessionHas('success');
    expect($this->mutual->run(fn () => $r->fresh()->only('status', 'decision_note')))->toBe(['status' => 'rejected', 'decision_note' => 'Facture en double']);
    as_('ctrl@equipe.test')->get($h . '/comptabilite/operations')->assertSee('Motif : Facture en double', false);
    as_('gest@equipe.test')->get($h . '/comptabilite/operations')->assertForbidden();
});

it('corrige une écriture validée par une extourne, elle aussi validée par une autre personne', function () {
    $h = $this->host;
    $e = $this->mutual->run(fn () => app(AccountingService::class)->post('2026-02-10', '6580', '571', 8000, 'Achat mal imputé', 'P-8'));

    as_('tres@equipe.test')->get($h . '/comptabilite/journal')->assertOk()->assertSee('Extourner');
    as_('tres@equipe.test')->post($h . "/comptabilite/ecritures/{$e->id}/extourne", ['date' => '2026-03-01'])->assertSessionHas('success');
    as_('tres@equipe.test')->post($h . "/comptabilite/ecritures/{$e->id}/extourne", ['date' => '2026-03-01'])->assertSessionHas('error');   // une seule
    $x = $this->mutual->run(fn () => AccountingEntry::where('kind', 'reversal')->firstOrFail());
    expect($x->debit_account_id)->toBe($e->credit_account_id)->and($x->credit_account_id)->toBe($e->debit_account_id)
        ->and($x->status)->toBe('captured')->and($x->reversed_entry_id)->toBe($e->id);
    as_('tres@equipe.test')->post($h . "/comptabilite/ecritures/{$x->id}/extourne", ['date' => '2026-03-02'])->assertSessionHas('error');   // pas d'extourne d'extourne

    as_('ctrl@equipe.test')->post($h . "/comptabilite/ecritures/{$x->id}/validation")->assertSessionHas('success');
    $rows = collect(balanceRows('2026-01-01', '2026-10-27')['rows'])->keyBy('number');
    expect($rows['6580'])->toMatchArray(['mov_d' => 8000, 'mov_c' => 8000, 'close_d' => 0, 'close_c' => 0]);
    as_('tres@equipe.test')->get($h . '/comptabilite/journal')->assertSee('Extournée');
});

it('gère le plan comptable : ajout, comptes usuels, renommage, suppression protégée', function () {
    $h = $this->host;
    as_('tres@equipe.test')->post($h . '/comptabilite/plan', ['number' => '6056', 'label' => 'Petit matériel'])->assertSessionHas('success');
    as_('tres@equipe.test')->post($h . '/comptabilite/plan', ['number' => '6056', 'label' => 'Doublon'])->assertSessionHasErrors('number');
    as_('tres@equipe.test')->post($h . '/comptabilite/plan', ['number' => '9011', 'label' => 'Analytique'])->assertSessionHasErrors('number');
    expect($this->mutual->run(fn () => Account::byNumber('6056')->class))->toBe(6);

    $before = $this->mutual->run(fn () => Account::count());
    as_('tres@equipe.test')->post($h . '/comptabilite/plan/comptes-usuels')->assertSessionHas('success');
    as_('tres@equipe.test')->post($h . '/comptabilite/plan/comptes-usuels')->assertSessionHas('success', 'Tous les comptes usuels sont déjà dans votre plan.');
    $after = $this->mutual->run(fn () => Account::count());
    expect($after)->toBeGreaterThan($before)->and($after)->toBeLessThanOrEqual($before + count(SyscohadaAccounts::USUAL));

    $a = $this->mutual->run(fn () => Account::byNumber('6056'));
    as_('tres@equipe.test')->put($h . "/comptabilite/plan/{$a->id}", ['label' => 'Petit matériel et outillage'])->assertSessionHas('success');
    as_('tres@equipe.test')->delete($h . "/comptabilite/plan/{$a->id}")->assertSessionHas('success');
    expect($this->mutual->run(fn () => Account::byNumber('6056')))->toBeNull();
    as_('tres@equipe.test')->delete($h . "/comptabilite/plan/{$this->ids['571']}")->assertSessionHas('error');   // compte du système
    as_('gest@equipe.test')->post($h . '/comptabilite/plan', ['number' => '6057', 'label' => 'X'])->assertForbidden();
    as_('tres@equipe.test')->get($h . '/comptabilite/plan')->assertOk()->assertSee('Ajouter un compte')->assertSee('Fournitures de bureau')->assertDontSee('Petit matériel');
});

it('clôture un exercice : écritures de clôture, verrouillage, états, réouverture et affectation', function () {
    $h = $this->host;
    seed2025();
    // Une saisie 2025 non décidée bloque la clôture.
    $pending = $this->mutual->run(fn () => app(AccountingService::class)->capture('2025-12-20', Account::byNumber('6580'), Account::byNumber('571'), 1000, 'À décider', 'P-X', 999));
    as_('tres@equipe.test')->get($h . '/comptabilite/cloture')->assertOk()->assertSee('attendent leur validation', false)->assertDontSee('Clôturer l’exercice 2025', false);
    as_('tres@equipe.test')->post($h . '/comptabilite/cloture/2025')->assertSessionHas('error');
    as_('tres@equipe.test')->post($h . '/comptabilite/cloture/2026')->assertSessionHas('error');   // exercice en cours
    as_('ctrl@equipe.test')->post($h . "/comptabilite/ecritures/{$pending->id}/rejet", ['note' => 'Saisie de test']);

    as_('tres@equipe.test')->get($h . '/comptabilite/cloture')->assertSee('Clôturer l’exercice 2025', false);
    as_('ctrl@equipe.test')->post($h . '/comptabilite/cloture/2025')->assertForbidden();
    as_('tres@equipe.test')->post($h . '/comptabilite/cloture/2025')->assertSessionHas('success');

    [$fy, $closing] = $this->mutual->run(fn () => [FiscalYear::where('label', '2025')->first(),
        AccountingEntry::where('kind', 'closing')->with(['debitAccount', 'creditAccount'])->get()
            ->map(fn ($e) => $e->debitAccount->number . '>' . $e->creditAccount->number . ':' . $e->amount_minor)->sort()->values()->all()]);
    expect($fy->status)->toBe('closed')->and($fy->result_minor)->toBe(30000)
        ->and($closing)->toBe(['130>131:30000', '130>6011:20000', '7561>130:50000']);

    // Verrouillage : plus rien ne peut être daté de 2025.
    expect(fn () => $this->mutual->run(fn () => app(AccountingService::class)->post('2025-12-30', '571', '7561', 100, 'Tardif')))->toThrow(\RuntimeException::class);
    as_('tres@equipe.test')->post($h . '/comptabilite/operations', ['date' => '2025-12-30', 'debit' => $this->ids['6580'], 'credit' => $this->ids['571'], 'amount' => '100', 'label' => 'Tardive'])
        ->assertSessionHas('error', 'L’exercice 2025 est clôturé : aucune écriture ne peut y être passée.');
    as_('tres@equipe.test')->post($h . '/tresorerie/mouvements', ['direction' => 'inflow', 'source' => 'other', 'mode' => 'cash', 'amount' => '100', 'moved_on' => '2025-12-30'])
        ->assertSessionHas('error');

    // États : 2025 présenté avant clôture ; 2026 repart du compte 131 (plus de report à nouveau calculé).
    $b2025 = collect(balanceRows('2025-01-01', '2025-12-31')['rows'])->keyBy('number');
    expect($b2025['7561'])->toMatchArray(['close_c' => 50000])->and($b2025->has('131'))->toBeFalse();
    $b2026 = balanceRows('2026-01-01', '2026-10-27');
    $r = collect($b2026['rows'])->keyBy('number');
    expect($b2026['balanced'])->toBeTrue()->and($r->has('12'))->toBeFalse()->and($r->has('7561'))->toBeFalse()
        ->and($r['131'])->toMatchArray(['open_c' => 30000, 'close_c' => 30000])->and($r['571'])->toMatchArray(['open_d' => 30000]);
    $sheet = $this->mutual->run(fn () => app(AccountingStatementsService::class)->balanceSheet(Carbon::parse('2026-10-27')));
    expect($sheet['balanced'])->toBeTrue()->and(collect($sheet['passif']['capitaux'])->pluck('amount', 'number')->all())->toBe(['131' => 30000, '13' => 0]);
    as_('tres@equipe.test')->get($h . '/comptabilite/balance?exercice=2025')->assertSee('Exercice 2025 clôturé', false);

    // Le budget 2025 garde son réalisé (les écritures de clôture ne sont pas du réalisé).
    $realized = $this->mutual->run(fn () => (fn () => $this->realized(2025))->call(app(\App\Http\Controllers\Tenant\BudgetController::class)));
    expect($realized[$this->ids['7561']])->toBe(50000)->and($realized[$this->ids['6011']])->toBe(20000);

    // Réouverture : administrateur seul, motif obligatoire.
    as_('tres@equipe.test')->post($h . '/comptabilite/cloture/2025/reouverture', ['note' => 'Erreur'])->assertForbidden();
    asAdmin()->post($h . '/comptabilite/cloture/2025/reouverture', ['note' => ''])->assertSessionHasErrors('note');
    asAdmin()->post($h . '/comptabilite/cloture/2025/reouverture', ['note' => 'Facture 2025 oubliée'])->assertSessionHas('success');
    expect($this->mutual->run(fn () => [AccountingEntry::where('kind', 'closing')->count(), FiscalYear::where('label', '2025')->first()->status,
        count(FiscalYear::where('label', '2025')->first()->history)]))->toBe([0, 'open', 2]);

    // Nouvelle clôture puis affectation du résultat (décision de l'AG) : la réouverture devient impossible.
    as_('tres@equipe.test')->post($h . '/comptabilite/cloture/2025')->assertSessionHas('success');
    as_('tres@equipe.test')->post($h . '/comptabilite/plan/comptes-usuels');
    as_('tres@equipe.test')->get($h . '/comptabilite/cloture')->assertSee('Préparer l’affectation du résultat', false);
    $ids = $this->mutual->run(fn () => Account::pluck('id', 'number'));
    as_('tres@equipe.test')->post($h . '/comptabilite/operations', ['date' => '2026-06-30', 'debit' => $ids['131'], 'credit' => $ids['121'], 'amount' => '30000', 'label' => 'Affectation du résultat 2025']);
    $aff = $this->mutual->run(fn () => AccountingEntry::where('label', 'Affectation du résultat 2025')->firstOrFail());
    as_('ctrl@equipe.test')->post($h . "/comptabilite/ecritures/{$aff->id}/validation")->assertSessionHas('success');
    asAdmin()->post($h . '/comptabilite/cloture/2025/reouverture', ['note' => 'Nouvelle erreur'])->assertSessionHas('error');
    $r = collect(balanceRows('2026-01-01', '2026-10-27')['rows'])->keyBy('number');
    expect($r['121'])->toMatchArray(['close_c' => 30000])->and($r['131'])->toMatchArray(['close_c' => 0, 'close_d' => 0]);
});

it('n’autorise pas la clôture d’un exercice si le précédent n’est pas clôturé', function () {
    $this->travelTo(Carbon::parse('2027-02-01 10:00:00'));
    seed2025();
    $this->mutual->run(fn () => app(AccountingService::class)->post('2026-05-05', '571', '7561', 10000, 'Cotisations 2026'));
    expect($this->mutual->run(fn () => app(AccountingService::class)->closingBlockers(2026)))->toContain('L’exercice 2025 doit être clôturé d’abord.');
    as_('tres@equipe.test')->post($this->host . '/comptabilite/cloture/2025')->assertSessionHas('success');
    as_('tres@equipe.test')->post($this->host . '/comptabilite/cloture/2026')->assertSessionHas('success');
    expect($this->mutual->run(fn () => FiscalYear::where('label', '2026')->first()->result_minor))->toBe(10000);
    asAdmin()->post($this->host . '/comptabilite/cloture/2025/reouverture', ['note' => 'Essai de réouverture'])
        ->assertSessionHas('error', 'L’exercice 2026 est clôturé : rouvrez-le d’abord.');
});
