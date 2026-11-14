<?php

/*
 * Mission « Mutuelle paramétrable », L6 (2026-11-22) : réinitialisation de la MUTUELLE TEST.
 * Garde-fous (mutuelle marquée test, confirmation, motif, sauvegarde préalable), ce qui est effacé
 * et ce qui est conservé, droits, traces. VRAIE base de mutuelle (ou tables préfixées).
 */

use App\Models\Central\MemberLink;
use App\Models\Tenant\ActType;
use App\Models\Tenant\Guarantee;
use App\Models\Tenant\GuaranteeVersion;
use App\Models\Tenant\Member;
use App\Models\Tenant\Subscription;
use App\Services\AccountingRefusal;
use App\Services\TestDataResetService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    provisionTestMutual();
    staffUser('tresor@equipe.test', ['treasurer_accountant']);
    $this->mutual->run(function () {
        $g = Guarantee::create(['code' => 'ESSAI', 'name' => 'Formule d’essai', 'status' => 'active']);
        $v = GuaranteeVersion::create(['guarantee_id' => $g->id, 'version_no' => 1, 'valid_from' => now()->subYear()->toDateString(),
            'base_contribution_minor' => 2000, 'periodicity' => 'monthly', 'coverage_rate' => 70, 'copay_rate' => 30, 'observation_days' => 0]);
        ActType::create(['code' => 'CONS', 'label' => 'Consultation', 'category' => 'Consultations']);
        foreach (['ESSAI-1', 'ESSAI-2'] as $code) {
            $m = Member::create(['member_code' => $code, 'first_name' => 'Awa', 'last_name' => 'Essai', 'birth_date' => '1990-01-01', 'sex' => 'F', 'status' => 'active', 'joined_at' => now()->subYear()->toDateString()]);
            Subscription::create(['member_id' => $m->id, 'guarantee_version_id' => $v->id, 'contribution_minor' => 2000, 'effective_date' => now()->subYear()->toDateString(), 'status' => 'validated', 'captured_by' => 1]);
        }
    });
    tenancy()->end();
    $acc = account('Awa');
    $this->memberId = $this->mutual->run(fn () => Member::where('member_code', 'ESSAI-1')->value('id'));
    tenancy()->end();
    MemberLink::create(['public_account_id' => $acc->id, 'mutual_id' => $this->mutual->id, 'member_id' => $this->memberId, 'member_code' => 'ESSAI-1']);
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('classe chaque table des migrations des mutuelles (une table inconnue bloquerait la réinitialisation)', function () {
    $created = [];
    foreach (glob(database_path('migrations/tenant/*.php')) as $f) {
        preg_match_all("/Schema::create\('([a-z_]+)'/", file_get_contents($f), $m);
        $created = array_merge($created, $m[1]);
    }
    $classified = array_merge(TestDataResetService::KEEP, TestDataResetService::FORMULAS, TestDataResetService::RESET);
    expect(array_values(array_diff(array_unique($created), $classified)))->toBe([]);
    expect(count($classified))->toBe(count(array_unique($classified)));   // aucune table dans deux listes
    expect(TestDataResetService::RESET)->not->toContain('users')->not->toContain('audits')->not->toContain('audit_logs');
    // Dans la vraie base de la mutuelle aussi : rien de non classé.
    expect(app(TestDataResetService::class)->inventory($this->mutual)['unclassified'])->toBe([]);
});

it('refuse hors MUTUELLE TEST, sans confirmation exacte ou sans motif — et n’efface alors rien', function () {
    $svc = app(TestDataResetService::class);
    expect(fn () => $svc->reset($this->mutual, $this->mutual->slug, 'Remise à zéro', [], null, 'test'))
        ->toThrow(AccountingRefusal::class, 'n’est pas marquée');
    $this->mutual->forceFill(['is_test' => true])->save();
    expect(fn () => $svc->reset($this->mutual, 'autre-mutuelle', 'Remise à zéro', [], null, 'test'))->toThrow(AccountingRefusal::class, 'Confirmation incorrecte');
    expect(fn () => $svc->reset($this->mutual, $this->mutual->slug, 'ok', [], null, 'test'))->toThrow(AccountingRefusal::class, 'motif');
    expect($this->mutual->run(fn () => Member::count()))->toBe(2);
    expect(MemberLink::where('mutual_id', $this->mutual->id)->count())->toBe(1);

    // Côté mutuelle : l'écran n'existe pas hors mutuelle de test.
    $this->mutual->forceFill(['is_test' => false])->save();
    asAdmin()->get($this->host . '/mutuelle-test')->assertNotFound();
});

it('efface l’activité, garde la configuration, sauvegarde avant et trace l’opération', function () {
    $this->mutual->forceFill(['is_test' => true])->save();
    $before = $this->mutual->run(fn () => ['users' => DB::table('users')->count(), 'accounts' => DB::table('accounts')->count(), 'roles' => DB::table('roles')->count()]);

    // Le trésorier n'y a pas accès ; l'administrateur voit l'inventaire.
    as_('tresor@equipe.test')->get($this->host . '/mutuelle-test')->assertForbidden();
    as_('tresor@equipe.test')->post($this->host . '/mutuelle-test/reinitialiser', ['confirm' => $this->mutual->slug, 'note' => 'Essai interdit'])->assertForbidden();
    asAdmin()->get($this->host . '/formules')->assertSee('Réinitialiser les données de test');
    asAdmin()->get($this->host . '/mutuelle-test')->assertOk()->assertSee('Adhérents')->assertSee('compte(s) adhérent relié(s)', false)->assertSee($this->mutual->slug);

    asAdmin()->post($this->host . '/mutuelle-test/reinitialiser', ['confirm' => 'faux', 'note' => 'Remise à zéro'])->assertSessionHasErrors('confirm');
    expect($this->mutual->run(fn () => Member::count()))->toBe(2);

    asAdmin()->post($this->host . '/mutuelle-test/reinitialiser', ['confirm' => $this->mutual->slug, 'note' => 'Remise à zéro avant formation'])
        ->assertRedirect()->assertSessionHas('success', fn ($m) => str_contains($m, 'Sauvegarde préalable'));
    tenancy()->end();

    $this->mutual->run(function () use ($before) {
        expect(Member::count())->toBe(0)->and(Subscription::count())->toBe(0);
        expect(DB::table('users')->count())->toBe($before['users'])->and(DB::table('accounts')->count())->toBe($before['accounts'])
            ->and(DB::table('roles')->count())->toBe($before['roles']);
        expect(ActType::count())->toBe(1)->and(Guarantee::where('code', 'ESSAI')->exists())->toBeTrue();   // formules conservées par défaut
        $log = DB::table('audit_logs')->where('action', 'test_data_reset')->first();
        expect($log)->not->toBeNull()->and(json_decode($log->after, true)['tables']['members'])->toBe(2);
    });
    expect(MemberLink::where('mutual_id', $this->mutual->id)->count())->toBe(0);
    $h = collect($this->mutual->fresh()->status_history)->last();
    expect($h['action'])->toBe('test_reset')->and($h['note'])->toContain('Remise à zéro avant formation')->toContain('administrateur de la mutuelle');

    // Sauvegarde préalable : fichier SQL de la mutuelle, avec les adhérents effacés.
    $file = collect(glob(TestDataResetService::backupDir() . '/' . $this->mutual->slug . '-*.sql'))->last();
    expect($file)->not->toBeNull();
    expect(file_get_contents($file))->toContain('ESSAI-1')->toContain('INSERT INTO');
    @unlink($file);
});

it('le super-administrateur réinitialise aussi les formules et recharge les formules d’exemple', function () {
    $this->mutual->forceFill(['is_test' => true])->save();
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    \Spatie\Permission\Models\Role::findOrCreate('super_admin', 'network');
    $admin = \App\Models\Central\NetworkUser::factory()->create(['mfa_enabled' => true]);
    $admin->assignRole('super_admin');
    $c = rtrim((string) config('app.url'), '/');
    $net = fn () => $this->actingAs($admin, 'network')->withSession(['mfa_passed' => true]);

    $net()->get("{$c}/reseau/mutuelles/{$this->mutual->id}")->assertOk()->assertSee('Réinitialiser les données de test')->assertSee('Adhérents : 2', false);
    $net()->post("{$c}/reseau/mutuelles/{$this->mutual->id}/test/reinitialiser", ['confirm' => $this->mutual->slug, 'note' => 'Nouvelle campagne d’essais', 'formulas' => 1, 'examples' => 1])
        ->assertSessionHas('success');
    tenancy()->end();
    $this->mutual->run(function () {
        expect(Member::count())->toBe(0);
        expect(Guarantee::withTrashed()->where('code', 'ESSAI')->exists())->toBeFalse();
        expect(Guarantee::pluck('code')->sort()->values()->all())->toBe(['EX-CONFORT', 'EX-ESSENTIEL', 'EX-FAMILLE', 'EX-PERSO', 'EX-SCOLAIRE', 'EX-SENIOR']);
    });
    expect(collect($this->mutual->fresh()->status_history)->last())->toMatchArray(['action' => 'test_reset', 'by' => $admin->id]);
    foreach (glob(TestDataResetService::backupDir() . '/' . $this->mutual->slug . '-*.sql') as $f) {
        @unlink($f);
    }
});

it('donne un libellé lisible à chaque table effacée', function () {
    foreach (array_merge(TestDataResetService::RESET, TestDataResetService::FORMULAS) as $t) {
        expect(TestDataResetService::LABELS)->toHaveKey($t);
    }
});
