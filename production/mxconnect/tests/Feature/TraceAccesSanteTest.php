<?php

/*
 * Mission « Mutuelle paramétrable », L6 (2026-11-22), Partie 7 : traçabilité des accès aux données
 * de santé. Une ouverture au détail médical est inscrite (qui, quoi, quand, IP) ; une personne non
 * habilitée ne voit pas le détail et n'est pas inscrite. VRAIE base de mutuelle (ou tables préfixées).
 */

use App\Models\Tenant\CareClaim;
use App\Models\Tenant\Member;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    provisionTestMutual();
    staffUser('tresor@equipe.test', ['treasurer_accountant']);
    staffUser('audit@equipe.test', ['auditor']);
    $this->claimId = $this->mutual->run(function () {
        $m = Member::create(['member_code' => 'MUT-SANTE', 'first_name' => 'Awa', 'last_name' => 'Test', 'birth_date' => '1990-01-01', 'sex' => 'F', 'status' => 'active', 'joined_at' => '2026-01-01']);

        return CareClaim::create(['member_id' => $m->id, 'opened_on' => now()->toDateString(), 'status' => 'open'])->id;
    });
    tenancy()->end();
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('trace l’ouverture d’un recours par une personne habilitée, une fois par tranche de 10 minutes', function () {
    $logs = fn () => $this->mutual->run(fn () => DB::table('audit_logs')->where('action', 'health_data_view')->get());

    asAdmin()->get($this->host . "/recours/{$this->claimId}")->assertOk();
    asAdmin()->get($this->host . "/recours/{$this->claimId}")->assertOk();
    $l = $logs();
    expect($l)->toHaveCount(1)->and($l[0]->object_type)->toBe('claim')->and((int) $l[0]->object_id)->toBe($this->claimId)->and($l[0]->ip)->not->toBeNull();

    // Le trésorier voit les montants, pas le détail médical : pas d'inscription.
    as_('tresor@equipe.test')->get($this->host . "/recours/{$this->claimId}")->assertOk();
    expect($logs())->toHaveCount(1);

    // Registre de la pharmacie : inscrit aussi.
    asAdmin()->get($this->host . '/pharmacie')->assertOk();
    expect($logs()->pluck('object_type')->all())->toContain('pharmacy_registry');

    // Le journal d'audit le montre (administrateur, auditeur) sans donnée médicale.
    as_('audit@equipe.test')->get($this->host . '/audit')->assertOk()
        ->assertSee('Accès aux données de santé')->assertSee('Recours #' . $this->claimId)->assertSee('Admin Test');
});
