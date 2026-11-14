<?php

/*
 * Portail prestataire V2 — phase 2 (2026-11-16) : espace prestataire central.
 * Comptes et établissements CENTRAUX ; soins dans une VRAIE base de mutuelle (mx_tests_tenant,
 * ou tables préfixées avec MX_TEST_STORAGE=prefix).
 */

use App\Models\Central\CareProvider;
use App\Models\Provider\ProviderAccount;
use App\Models\Provider\ProviderAffiliation;
use App\Models\Provider\ProviderDocument;
use App\Models\Provider\ProviderHandoff;
use App\Models\Provider\ProviderMembership;
use App\Models\Tenant\ActType;
use App\Models\Tenant\Guarantee;
use App\Models\Tenant\GuaranteeVersion;
use App\Models\Tenant\Member;
use App\Models\Tenant\Prestation;
use App\Models\Tenant\ProviderConvention;
use App\Models\Tenant\ProviderInvoice;
use App\Models\Tenant\RightsCheck;
use App\Models\Tenant\Subscription;
use App\Models\Tenant\User;
use App\Support\ProviderAccess;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    provisionTestMutual();
    $this->c = rtrim((string) config('app.url'), '/');   // domaine principal (espace central)
    [$this->member, $this->act] = $this->mutual->run(function () {
        $g = Guarantee::create(['code' => 'ESS', 'name' => 'Essentielle', 'status' => 'active']);
        $v = GuaranteeVersion::create(['guarantee_id' => $g->id, 'version_no' => 1, 'valid_from' => '2026-01-01', 'base_contribution_minor' => 1000,
            'periodicity' => 'monthly', 'coverage_rate' => 80, 'copay_rate' => 20, 'observation_days' => 0]);
        $m = Member::create(['member_code' => 'MUT-000777', 'first_name' => 'Awa', 'last_name' => 'Ngono', 'birth_date' => '1990-04-02',
            'sex' => 'F', 'status' => 'active', 'joined_at' => '2026-01-01']);
        Subscription::create(['member_id' => $m->id, 'guarantee_version_id' => $v->id, 'contribution_minor' => 1000, 'effective_date' => '2026-01-01',
            'observation_ends_on' => '2026-01-01', 'status' => 'validated', 'captured_by' => 1]);
        $act = ActType::create(['code' => 'CONS', 'label' => 'Consultation de médecine générale', 'category' => 'consultation']);

        return [$m, $act];
    });
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

/** Personne de l'espace prestataire, prête (mot de passe choisi, double authentification active). */
function proAccount(string $email, int $providerId, string $role = 'admin', ?array $perms = null): ProviderAccount
{
    if (tenancy()->initialized) {
        tenancy()->end();
    }
    $a = ProviderAccount::firstOrCreate(['email' => $email], ['first_name' => ucfirst(strtok($email, '@')), 'last_name' => 'Soignant',
        'password' => 'MotDePasse-Pro-2026', 'active' => true, 'mfa_enabled' => true, 'mfa_secret' => 'JBSWY3DPEHPK3PXP']);
    ProviderMembership::create(['provider_account_id' => $a->id, 'care_provider_id' => $providerId, 'role' => $role, 'active' => true, 'permissions' => $perms]);

    return $a;
}

/** Requêtes dans l'espace central au nom d'une personne (double authentification passée). */
function asPro(ProviderAccount $a): \Illuminate\Foundation\Testing\TestCase
{
    if (tenancy()->initialized) {
        tenancy()->end();
    }

    return test()->actingAs($a->fresh(), 'provider')->withSession(['provider_mfa_passed' => true]);
}

/** Établissement conventionné par la mutuelle de test (via le registre de la mutuelle). */
function conventioned(string $name = 'CSI Akono'): ProviderConvention
{
    asAdmin()->post(test()->host . '/parametres/prestataires', ['mode' => 'new', 'name' => $name, 'type' => 'health_center', 'locality' => 'Yaoundé'])->assertRedirect();

    return test()->mutual->run(fn () => ProviderConvention::latest('id')->firstOrFail());
}

/** Adresse d'entrée (sur le sous-domaine de la mutuelle) renvoyée par l'espace central, sans le port local. */
function entryUrl($res): string
{
    $loc = (string) $res->headers->get('Location');
    expect(parse_url($loc, PHP_URL_HOST))->toBe(parse_url(test()->host, PHP_URL_HOST))
        ->and(parse_url($loc, PHP_URL_PATH))->toMatch('~^/prestataire/entree/[A-Za-z0-9]{64}$~');

    return test()->host . parse_url($loc, PHP_URL_PATH);
}

/** Entrée dans le portail de la mutuelle depuis l'espace central ; renvoie le compte de la mutuelle ouvert. */
function enterPortal(ProviderAccount $a, string $target = 'verify'): User
{
    $res = asPro($a)->post(test()->c . "/espace-prestataire/ouvrir/{$target}/" . test()->mutual->id);
    $url = entryUrl($res);
    test()->get($url)->assertRedirect();

    return test()->mutual->run(fn () => User::where('provider_account_id', $a->id)->firstOrFail());
}

it('inscrit un établissement, refuse puis accepte son affiliation avec un motif et crée la convention', function () {
    tenancy()->end();
    $country = \App\Models\Central\Country::where('active', true)->firstOrFail();
    $this->post($this->c . '/espace-prestataire/inscription', [
        'establishment' => 'Clinique de l’Espoir', 'type' => 'clinic', 'country_id' => $country->id, 'locality' => 'Mvog-Mbi',
        'approval_number' => 'AGR-2026-15', 'first_name' => 'Jeanne', 'last_name' => 'Mballa', 'email' => 'Direction@Espoir.test',
        'password' => 'Espoir-2026-solide', 'password_confirmation' => 'Espoir-2026-solide', 'mutuals' => [$this->mutual->id],
        'message' => 'Maternité et pédiatrie', 'attest' => '1',
    ])->assertRedirect($this->c . '/espace-prestataire/double-authentification');

    $acc = ProviderAccount::where('email', 'direction@espoir.test')->firstOrFail();
    $p = CareProvider::where('name', 'Clinique de l’Espoir')->firstOrFail();
    expect($p->registration_status)->toBe('pending')
        ->and(ProviderMembership::where('provider_account_id', $acc->id)->value('role'))->toBe('admin');
    $aff = ProviderAffiliation::where('care_provider_id', $p->id)->firstOrFail();
    expect($aff->status)->toBe('requested');

    // La mutuelle voit la demande ; un refus exige un motif, communiqué à l'établissement.
    asAdmin()->get($this->host . '/parametres/prestataires')->assertSee('Demandes d’affiliation reçues', false)->assertSee('Clinique de l’Espoir', false)->assertSee('Maternité et pédiatrie');
    asAdmin()->post($this->host . "/parametres/prestataires/demandes/{$aff->id}/refuser", ['note' => ''])->assertSessionHasErrors('note');
    asAdmin()->post($this->host . "/parametres/prestataires/demandes/{$aff->id}/refuser", ['note' => 'Agrément à fournir'])->assertSessionHas('success');
    tenancy()->end();
    expect($aff->fresh()->status)->toBe('rejected');
    $acc->forceFill(['mfa_enabled' => true, 'mfa_secret' => 'JBSWY3DPEHPK3PXP'])->save();
    asPro($acc)->get($this->c . '/espace-prestataire/mutuelles')->assertSee('Demande refusée')->assertSee('Motif : Agrément à fournir');

    // Nouvelle demande, acceptée : la convention est créée DANS la mutuelle, l'index central suit.
    asPro($acc)->post($this->c . '/espace-prestataire/affiliations', ['mutual_id' => $this->mutual->id, 'message' => 'Agrément joint'])->assertSessionHas('success');
    expect($aff->fresh()->status)->toBe('requested');
    asAdmin()->post($this->host . "/parametres/prestataires/demandes/{$aff->id}/accepter", ['reference' => 'CONV-77', 'payment_days' => 30])->assertRedirect();
    $conv = $this->mutual->run(fn () => ProviderConvention::where('care_provider_id', $p->id)->firstOrFail());
    expect($conv->reference)->toBe('CONV-77')->and($conv->payment_days)->toBe(30)->and($conv->active)->toBeTrue();
    tenancy()->end();
    expect($aff->fresh()->status)->toBe('approved')->and($p->fresh()->registration_status)->toBe('approved');
    asPro($acc)->get($this->c . '/espace-prestataire/mutuelles/' . $this->mutual->id)->assertOk()->assertSee('CONV-77')->assertSee('30 jours');
});

it('impose mot de passe provisoire puis double authentification, et cloisonne les routes par droit', function () {
    $conv = conventioned();
    tenancy()->end();
    $a = ProviderAccount::create(['first_name' => 'Paul', 'last_name' => 'Ondoa', 'email' => 'paul@csi.test', 'password' => 'Provisoire-1234',
        'must_change_password' => true, 'active' => true]);
    ProviderMembership::create(['provider_account_id' => $a->id, 'care_provider_id' => $conv->care_provider_id, 'role' => 'doctor', 'active' => true]);

    $this->post($this->c . '/espace-prestataire/connexion', ['email' => 'paul@csi.test', 'password' => 'Provisoire-1234'])->assertRedirect();
    $this->actingAs($a, 'provider')->get($this->c . '/espace-prestataire')->assertRedirect($this->c . '/espace-prestataire/mot-de-passe');
    $a->forceFill(['must_change_password' => false])->save();
    $this->actingAs($a->fresh(), 'provider')->get($this->c . '/espace-prestataire')->assertRedirect($this->c . '/espace-prestataire/double-authentification');
    $a->forceFill(['mfa_enabled' => true, 'mfa_secret' => 'JBSWY3DPEHPK3PXP'])->save();
    $this->actingAs($a->fresh(), 'provider')->get($this->c . '/espace-prestataire')->assertRedirect($this->c . '/espace-prestataire/verification');

    // Médecin : tableau de bord oui ; personnel, coordonnées bancaires, factures : non.
    asPro($a)->get($this->c . '/espace-prestataire')->assertOk()->assertSee('CSI Akono')->assertDontSee('Personnel et utilisateurs');
    asPro($a)->get($this->c . '/espace-prestataire/personnel')->assertForbidden();
    asPro($a)->post($this->c . '/espace-prestataire/etablissement/banque', ['bank_name' => 'X'])->assertForbidden();
    asPro($a)->get($this->c . '/espace-prestataire/ouvrir/invoices')->assertForbidden();
    asPro($a)->get($this->c . '/espace-prestataire/ouvrir/verify')->assertOk()->assertSee('Mutuelle Équipe', false);

    // Compte désactivé : déconnecté immédiatement.
    $a->forceFill(['active' => false])->save();
    asPro($a)->get($this->c . '/espace-prestataire')->assertRedirect($this->c . '/espace-prestataire/connexion');
});

it('ouvre le portail de la mutuelle par un jeton à usage unique, au nom de la personne, avec ses droits', function () {
    $conv = conventioned();
    $doc = proAccount('dr.essomba@csi.test', $conv->care_provider_id, 'doctor');
    staffUser('ctrl@equipe.test', ['controller_validator']);

    $user = enterPortal($doc);
    expect($user->care_provider_id)->toBe($conv->care_provider_id)->and($user->hasRole('provider'))->toBeTrue()
        ->and($user->email)->toEndWith('@prestataires.mx-connect.invalid');
    // Jeton déjà utilisé : refusé.
    $h = ProviderHandoff::firstOrFail();
    expect($h->used_at)->not->toBeNull();

    // Le compte de la mutuelle ne s'ouvre jamais par mot de passe.
    // (même si quelqu'un lui fixait un mot de passe côté mutuelle : refus, renvoi vers l'espace prestataire)
    $this->mutual->run(fn () => $user->forceFill(['password' => 'Connu-1234567'])->save());
    app('auth')->forgetGuards();
    $this->flushSession();
    $r = $this->post($this->host . '/connexion', ['email' => $user->email, 'password' => 'Connu-1234567']);
    $r->assertSessionHasErrors(['email' => 'Ce compte s’utilise depuis l’espace prestataire MX-CONNECT : connectez-vous sur ' . $this->c . '/espace-prestataire.']);

    // Vérifier, déclarer : la saisie est attribuée à la personne ; la mutuelle valide (4 yeux).
    as_($user->email)->post($this->host . '/prestataire/verifier', ['code' => 'MUT-000777'])->assertSee('Droits ouverts')->assertSee('Retour à l’espace prestataire', false);
    $check = $this->mutual->run(fn () => RightsCheck::where('ok', true)->latest('id')->firstOrFail());
    as_($user->email)->post($this->host . "/prestataire/declarer/{$check->id}", ['beneficiary' => 'member', 'act_type_id' => $this->act->id,
        'care_date' => now()->toDateString(), 'total' => '10 000'])->assertRedirect($this->host . '/prestataire/prises-en-charge');
    $p = $this->mutual->run(fn () => Prestation::firstOrFail());
    expect($p->captured_by)->toBe($user->id)->and($p->source)->toBe('provider');
    // Un médecin ne facture pas (droit « invoices.view/bill » absent) : la route est fermée.
    as_($user->email)->get($this->host . '/prestataire/factures')->assertForbidden();

    // Le caissier vérifie les droits mais ne déclare pas : pas de bouton, route fermée.
    tenancy()->end();
    $cash = proAccount('accueil@csi.test', $conv->care_provider_id, 'cashier');
    $cu = enterPortal($cash);
    as_($cu->email)->post($this->host . '/prestataire/verifier', ['code' => 'MUT-000777'])->assertSee('Droits ouverts')->assertDontSee('Déclarer une prise en charge')
        ->assertSee('ne comprend pas la déclaration', false);
    $check2 = $this->mutual->run(fn () => RightsCheck::where('ok', true)->latest('id')->firstOrFail());
    as_($cu->email)->get($this->host . "/prestataire/declarer/{$check2->id}")->assertForbidden();
    as_($cu->email)->get($this->host . '/prestataire/factures')->assertOk();
});

it('refuse un jeton expiré ou réutilisé, et ferme la session quand l’établissement retire l’accès', function () {
    $conv = conventioned();
    $nurse = proAccount('inf@csi.test', $conv->care_provider_id, 'nurse');
    $url = asPro($nurse)->post($this->c . '/espace-prestataire/ouvrir/verify/' . $this->mutual->id)->headers->get('Location');
    $url = test()->host . parse_url($url, PHP_URL_PATH);
    $this->get($url)->assertRedirect();
    $this->get($url)->assertForbidden()->assertSee('déjà servi', false);

    tenancy()->end();
    $url2 = entryUrl(asPro($nurse)->post($this->c . '/espace-prestataire/ouvrir/verify/' . $this->mutual->id));
    ProviderHandoff::whereNull('used_at')->update(['expires_at' => now()->subSecond()]);
    $this->get($url2)->assertForbidden();

    // L'administrateur retire « vérifier les droits » : plus d'entrée ; puis désactive : session fermée.
    $user = $this->mutual->run(fn () => User::where('provider_account_id', $nurse->id)->firstOrFail());
    tenancy()->end();
    $m = ProviderMembership::where('provider_account_id', $nurse->id)->firstOrFail();
    $m->update(['permissions' => ['deny' => ['rights.verify']]]);
    asPro($nurse)->post($this->c . '/espace-prestataire/ouvrir/verify/' . $this->mutual->id)->assertSessionHas('error');
    as_($user->email)->get($this->host . '/prestataire')->assertForbidden();
    tenancy()->end();
    $m->update(['active' => false, 'permissions' => null]);
    as_($user->email)->get($this->host . '/prestataire/prises-en-charge')->assertRedirect($this->c . '/espace-prestataire/connexion');

    // La mutuelle peut aussi couper l'accès de son côté (compte désactivé dans « Équipe et rôles »).
    tenancy()->end();
    $m->update(['active' => true]);
    $this->mutual->run(fn () => $user->forceFill(['active' => false])->save());
    $url3 = entryUrl(asPro($nurse)->post($this->c . '/espace-prestataire/ouvrir/verify/' . $this->mutual->id));
    $this->get($url3)->assertForbidden()->assertSee('La mutuelle a désactivé votre accès', false);
});

it('gère le personnel : mot de passe provisoire affiché une fois, droits ajoutés et retirés, dernier administrateur protégé', function () {
    $conv = conventioned();
    $admin = proAccount('direction@csi.test', $conv->care_provider_id, 'admin');
    asPro($admin)->post($this->c . '/espace-prestataire/personnel', ['first_name' => 'Marie', 'last_name' => 'Abena', 'email' => 'Marie@CSI.test',
        'role' => 'billing', 'job_title' => 'Facturation'])->assertSessionHas('temp_password');
    $marie = ProviderAccount::where('email', 'marie@csi.test')->firstOrFail();
    expect($marie->must_change_password)->toBeTrue();
    $mm = ProviderMembership::where('provider_account_id', $marie->id)->firstOrFail();
    expect(ProviderAccess::effective('billing', []))->toContain('invoices.bill')->not->toContain('rights.verify');

    // Ajouter « vérifier les droits », retirer « demander le paiement ».
    $checked = array_values(array_diff(array_merge(ProviderAccess::defaults('billing'), ['rights.verify']), ['invoices.bill']));
    asPro($admin)->post($this->c . "/espace-prestataire/personnel/{$mm->id}", ['role' => 'billing', 'active' => '1', 'perms_sent' => '1', 'perms' => $checked])->assertSessionHas('success');
    expect($mm->fresh()->effectivePermissions())->toContain('rights.verify')->not->toContain('invoices.bill')
        ->and($mm->fresh()->permissions)->toBe(['grant' => ['rights.verify'], 'deny' => ['invoices.bill']]);
    // « Gérer le personnel » ne s'accorde pas individuellement.
    asPro($admin)->post($this->c . "/espace-prestataire/personnel/{$mm->id}", ['role' => 'billing', 'active' => '1', 'perms_sent' => '1', 'perms' => ['staff.manage']]);
    expect($mm->fresh()->can('staff.manage'))->toBeFalse();

    // Le dernier administrateur ne peut pas se rétrograder.
    $am = ProviderMembership::where('provider_account_id', $admin->id)->firstOrFail();
    asPro($admin)->post($this->c . "/espace-prestataire/personnel/{$am->id}", ['role' => 'doctor', 'active' => '1'])->assertSessionHas('error');
    expect($am->fresh()->role)->toBe('admin');

    // Réinitialisation : mot de passe provisoire + double authentification à refaire.
    $marie->forceFill(['must_change_password' => false, 'mfa_enabled' => true, 'mfa_secret' => 'JBSWY3DPEHPK3PXP'])->save();
    asPro($admin)->post($this->c . "/espace-prestataire/personnel/{$mm->id}/mot-de-passe")->assertSessionHas('temp_password');
    expect($marie->fresh()->must_change_password)->toBeTrue()->and($marie->fresh()->mfa_enabled)->toBeFalse();

    // Une personne déjà inscrite ailleurs est simplement rattachée (pas de nouveau mot de passe).
    $other = CareProvider::create(['name' => 'Pharmacie du Lac', 'type' => 'pharmacy', 'active' => true]);
    $ph = proAccount('pharma@lac.test', $other->id, 'pharmacist');
    asPro($admin)->post($this->c . '/espace-prestataire/personnel', ['first_name' => 'X', 'last_name' => 'Y', 'email' => 'pharma@lac.test', 'role' => 'pharmacist'])
        ->assertSessionMissing('temp_password')->assertSessionHas('success');
    expect(ProviderMembership::where('provider_account_id', $ph->id)->count())->toBe(2);
    // … et son mot de passe ne se réinitialise pas depuis un autre établissement.
    $pm = ProviderMembership::where('provider_account_id', $ph->id)->where('care_provider_id', $conv->care_provider_id)->firstOrFail();
    asPro($admin)->post($this->c . "/espace-prestataire/personnel/{$pm->id}/mot-de-passe")->assertSessionHas('error');
});

it('affiche un tableau de bord calculé sur les vraies données de la mutuelle, montants par devise', function () {
    $conv = conventioned();
    $this->mutual->run(fn () => $conv->update(['payment_days' => 15]));
    $doc = proAccount('dr@csi.test', $conv->care_provider_id, 'doctor');
    $admin = proAccount('direction@csi.test', $conv->care_provider_id, 'admin');
    staffUser('ctrl@equipe.test', ['controller_validator']);
    staffUser('treso@equipe.test', ['treasurer_accountant']);
    $u = enterPortal($doc);

    // 2 consultations déclarées ; 1 validée et facturée puis payée ; 1 rejetée.
    foreach (['10 000', '6 000'] as $i => $total) {
        as_($u->email)->post($this->host . '/prestataire/verifier', ['code' => 'MUT-000777']);
        $check = $this->mutual->run(fn () => RightsCheck::where('ok', true)->latest('id')->firstOrFail());
        as_($u->email)->post($this->host . "/prestataire/declarer/{$check->id}", ['beneficiary' => 'member', 'act_type_id' => $this->act->id,
            'care_date' => now()->toDateString(), 'total' => $total] + ($i ? ['confirm_distinct' => '1'] : []))->assertRedirect();
    }
    [$p1, $p2] = $this->mutual->run(fn () => Prestation::orderBy('id')->get()->all());
    as_('ctrl@equipe.test')->post($this->host . "/recours/{$p1->care_claim_id}/prestations/{$p1->id}/validation")->assertSessionHas('success');
    as_('ctrl@equipe.test')->post($this->host . "/recours/{$p2->care_claim_id}/prestations/{$p2->id}/rejet", ['note' => 'Doublon'])->assertSessionHas('success');
    $bill = proAccount('fact@csi.test', $conv->care_provider_id, 'billing');
    $bu = enterPortal($bill, 'invoices');
    as_($bu->email)->post($this->host . "/prestataire/factures/{$p1->care_claim_id}", ['provider_reference' => 'F-01'])->assertSessionHas('success');
    $inv = $this->mutual->run(fn () => ProviderInvoice::firstOrFail());
    expect($inv->amount_minor)->toBe(8000);   // 80 % de 10 000
    // Facture ancienne de 20 jours (> délai de paiement de 15 jours) : alerte « en retard ».
    $this->mutual->run(fn () => ProviderInvoice::whereKey($inv->id)->update(['created_at' => now()->subDays(20)]));

    $page = asPro($admin)->get($this->c . '/espace-prestataire')->assertOk();
    $page->assertSee('Patients identifiés aujourd’hui', false)->assertSee('Prises en charge validées')->assertSee('Consultation de médecine générale')
        ->assertSee('au-delà du délai de paiement de la convention (15 jours)', false)->assertSee('Montants en');
    $d = app(\App\Services\Provider\ProviderDashboardService::class)->build(CareProvider::findOrFail($conv->care_provider_id));
    expect($d['counts'])->toMatchArray(['patients_today' => 1, 'pending' => 0, 'validated' => 1, 'rejected' => 1, 'consultations' => 1,
        'inv_total' => 1, 'inv_accepted' => 0, 'inv_rejected' => 0, 'inv_paid' => 0]);
    $money = array_values($d['money'])[0];
    expect($money['billed'])->toBe(8000)->and($money['due'])->toBe(8000)->and($money['paid'])->toBe(0);

    // Paiement réel enregistré par le trésorier : « payé » seulement maintenant.
    as_('treso@equipe.test')->post($this->host . "/recours/{$p1->care_claim_id}/facture/{$inv->id}/paiement", ['mode' => 'bank'])->assertRedirect();
    tenancy()->end();
    \Illuminate\Support\Facades\Cache::flush();
    $d = app(\App\Services\Provider\ProviderDashboardService::class)->build(CareProvider::findOrFail($conv->care_provider_id));
    $money = array_values($d['money'])[0];
    expect($money['paid'])->toBe(8000)->and($money['due'])->toBe(0)->and($d['counts']['inv_paid'])->toBe(1)->and($d['avgDelay'])->not->toBeNull();

    // Le médecin voit l'activité mais pas les montants.
    asPro($doc)->get($this->c . '/espace-prestataire')->assertOk()->assertDontSee('Montants en');
});

it('ouvre l’accès du responsable depuis la mutuelle, garde les notes internes privées et chiffre les coordonnées bancaires', function () {
    $conv = conventioned();
    $this->mutual->run(fn () => $conv->update(['notes' => 'Négociation tarifaire confidentielle', 'billing_terms' => 'Factures mensuelles avant le 5']));
    asAdmin()->post($this->host . "/parametres/prestataires/{$conv->id}/acces-responsable", ['first_name' => 'Rose', 'last_name' => 'Atangana', 'email' => 'rose@csi.test'])
        ->assertSessionHas('temp_password');
    tenancy()->end();
    $rose = ProviderAccount::where('email', 'rose@csi.test')->firstOrFail();
    expect($rose->must_change_password)->toBeTrue()
        ->and(ProviderMembership::where('provider_account_id', $rose->id)->value('role'))->toBe('admin');
    // Un 2e accès n'est plus proposé : l'établissement gère son personnel.
    asAdmin()->post($this->host . "/parametres/prestataires/{$conv->id}/acces-responsable", ['first_name' => 'A', 'last_name' => 'B', 'email' => 'b@csi.test'])->assertSessionHas('error');

    tenancy()->end();
    $rose->forceFill(['must_change_password' => false, 'mfa_enabled' => true, 'mfa_secret' => 'JBSWY3DPEHPK3PXP'])->save();
    asPro($rose)->get($this->c . '/espace-prestataire/mutuelles/' . $this->mutual->id)->assertOk()
        ->assertSee('Factures mensuelles avant le 5')->assertDontSee('Négociation tarifaire confidentielle')->assertSee('Non défini');

    asPro($rose)->post($this->c . '/espace-prestataire/etablissement/banque', ['bank_name' => 'Afriland', 'account_number' => 'CM21 10005 00001 12345678901 23'])->assertSessionHas('success');
    $raw = DB::connection('central')->table('care_providers')->where('id', $conv->care_provider_id)->value('bank_details');
    expect($raw)->not->toContain('12345678901')
        ->and(CareProvider::find($conv->care_provider_id)->bank_details['account_number'])->toBe('CM21 10005 00001 12345678901 23');
    asPro($rose)->post($this->c . '/espace-prestataire/etablissement', ['name' => 'CSI Akono', 'type' => 'health_center', 'specialties' => 'Pédiatrie, Maternité',
        'latitude' => '3.848', 'longitude' => '11.502', 'hours' => ['mon' => '7 h 30 – 18 h']])->assertSessionHas('success');
    expect(CareProvider::find($conv->care_provider_id)->specialties)->toBe(['Pédiatrie', 'Maternité']);
});

it('n’accepte que de vrais PDF pour les documents, et ne les sert qu’à l’établissement', function () {
    $conv = conventioned();
    $admin = proAccount('direction@csi.test', $conv->care_provider_id, 'admin');
    asPro($admin)->post($this->c . '/espace-prestataire/documents', ['kind' => 'approval', 'title' => 'Agrément',
        'file' => UploadedFile::fake()->createWithContent('a.pdf', 'pas un pdf')])->assertSessionHasErrors('file');
    asPro($admin)->post($this->c . '/espace-prestataire/documents', ['kind' => 'approval', 'title' => 'Agrément du ministère', 'reference' => 'AGR-9',
        'expires_on' => now()->addDays(20)->toDateString(), 'file' => pdf('agrement.pdf')])->assertSessionHas('success');
    $doc = ProviderDocument::firstOrFail();
    expect(CareProvider::find($conv->care_provider_id)->approval_expires_on->toDateString())->toBe(now()->addDays(20)->toDateString());
    asPro($admin)->get($this->c . '/espace-prestataire/documents/' . $doc->id)->assertOk()->assertHeader('Content-Security-Policy', 'sandbox');
    asPro($admin)->get($this->c . '/espace-prestataire')->assertSee('Agrément expirant le', false);

    $other = CareProvider::create(['name' => 'Laboratoire Central', 'type' => 'laboratory', 'active' => true]);
    $intrus = proAccount('labo@central.test', $other->id, 'admin');
    asPro($intrus)->get($this->c . '/espace-prestataire/documents/' . $doc->id)->assertNotFound();
});

it('donne un droit explicite à chaque route de l’espace prestataire', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => in_array(\App\Http\Middleware\ProviderSpaceGuard::class, $r->gatherMiddleware(), true))
        ->map(fn ($r) => (string) $r->getName())->values()->all();
    expect($routes)->not->toBeEmpty();
    expect(array_diff($routes, array_keys(ProviderAccess::ROUTES)))->toBe([]);
});
