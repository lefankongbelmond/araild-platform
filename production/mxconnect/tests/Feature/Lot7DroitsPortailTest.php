<?php

/*
 * Lot 7 — matrice des droits appliquée à chaque route, auditeur, contrôle « 4 yeux »
 * renforcé, double authentification pour tous, et portail des prestataires de soins.
 * Testé dans une VRAIE base de mutuelle (mx_tests_tenant, provisionnée comme sur LWS).
 */

use App\Models\Tenant\ActType;
use App\Models\Tenant\CareClaim;
use App\Models\Tenant\Guarantee;
use App\Models\Tenant\GuaranteeVersion;
use App\Models\Tenant\Incident;
use App\Models\Tenant\Member;
use App\Models\Tenant\Prestation;
use App\Models\Tenant\ProviderConvention;
use App\Models\Tenant\ProviderInvoice;
use App\Models\Tenant\RightsCheck;
use App\Models\Tenant\Subscription;
use App\Models\Tenant\TreasuryMovement;
use App\Models\Tenant\User;
use App\Support\TenantAccess;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    provisionTestMutual();
    // Un adhérent couvert à 80 % et un acte.
    [$this->member, $this->act] = $this->mutual->run(function () {
        $g = Guarantee::create(['code' => 'ESS', 'name' => 'Essentielle', 'status' => 'active']);
        $v = GuaranteeVersion::create(['guarantee_id' => $g->id, 'version_no' => 1, 'valid_from' => '2026-01-01', 'base_contribution_minor' => 1000,
            'periodicity' => 'monthly', 'coverage_rate' => 80, 'copay_rate' => 20, 'observation_days' => 0]);
        $m = Member::create(['member_code' => 'MUT-000777', 'first_name' => 'Awa', 'last_name' => 'Nkolo', 'birth_date' => '1990-04-02',
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

/** Convention + compte prestataire prêt à l'emploi. */
function providerAccount(string $email, string $name): array
{
    asAdmin()->post(test()->host . '/parametres/prestataires', ['mode' => 'new', 'name' => $name, 'type' => 'health_center', 'locality' => 'Yaoundé'])->assertRedirect();
    $conv = test()->mutual->run(fn () => ProviderConvention::latest('id')->firstOrFail());
    $user = staffUser($email, ['provider'], ['care_provider_id' => $conv->care_provider_id]);

    return [$conv, $user];
}

it('donne une règle explicite à chaque route du back-office (le reste : administrateur seul)', function () {
    $adminOnly = ['guarantees.create', 'guarantees.store', 'guarantees.version', 'antennas.store', 'antennas.update', 'antennas.destroy',
        'act-types.store', 'act-types.update', 'medicines.store', 'medicines.update', 'medicines.destroy', 'catalog.import', 'catalog.import.store'];
    $unruled = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => in_array(\App\Http\Middleware\TenantRouteAccess::class, $r->gatherMiddleware(), true))
        ->map(fn ($r) => (string) $r->getName())
        ->filter(fn ($n) => TenantAccess::ruleFor($n) === null)->values()->all();
    expect($unruled)->toEqualCanonicalizing($adminOnly);
    foreach ($adminOnly as $r) {
        expect(TenantAccess::rolesFor($r))->toBe(['mutual_admin']);
    }
});

it('refuse une rubrique fermée à un rôle même en tapant son adresse', function () {
    staffUser('audit@equipe.test', ['auditor']);
    staffUser('cm@equipe.test', ['community_manager']);
    staffUser('tres@equipe.test', ['treasurer_accountant']);
    staffUser('agent@equipe.test', ['enrollment_agent']);
    staffUser('gest@equipe.test', ['benefits_manager']);
    $h = $this->host;
    $cases = [
        ['audit@equipe.test', '/tresorerie', 200], ['audit@equipe.test', '/audit', 200], ['audit@equipe.test', '/budget', 200],
        ['audit@equipe.test', '/recours', 403], ['audit@equipe.test', '/adherents/creer', 403], ['audit@equipe.test', '/reseau-social', 403],
        ['cm@equipe.test', '/tresorerie', 403], ['cm@equipe.test', '/comptabilite/journal', 403], ['cm@equipe.test', '/audit', 403],
        ['cm@equipe.test', '/adherents', 403], ['cm@equipe.test', '/reseau-social', 200], ['cm@equipe.test', '/signaler', 200],
        ['tres@equipe.test', '/recours', 200], ['tres@equipe.test', '/cotisations', 200], ['tres@equipe.test', '/equipe', 403], ['tres@equipe.test', '/alertes', 403],
        ['agent@equipe.test', '/recours', 403], ['agent@equipe.test', '/adherents', 200], ['agent@equipe.test', '/tresorerie', 403],
        ['gest@equipe.test', '/recours/creer', 200], ['gest@equipe.test', '/tresorerie', 403],
        ['admin@equipe.test', '/recours/creer', 403], ['admin@equipe.test', '/prestataire', 403], ['admin@equipe.test', '/parametres/prestataires', 200],
    ];
    foreach ($cases as [$who, $url, $status]) {
        expect(as_($who)->get($h . $url)->status())->toBe($status, "{$who} {$url}");
    }
    // Le menu ne montre que ce qui est ouvert, et l'accueil après connexion est la 1re rubrique permise.
    as_('cm@equipe.test')->get($h . '/reseau-social')->assertDontSee('/tresorerie"', false)->assertSee('/signaler"', false);
    expect(TenantAccess::home(tenantUser('audit@equipe.test')))->toBe($h . '/adherents');
});

it('montre au trésorier les montants d’un recours, jamais son détail médical', function () {
    staffUser('tres@equipe.test', ['treasurer_accountant']);
    staffUser('gest@equipe.test', ['benefits_manager']);
    $claim = $this->mutual->run(fn () => CareClaim::create(['member_id' => $this->member->id, 'opened_on' => now()->toDateString(), 'reason' => 'Paludisme grave', 'status' => 'open']));
    as_('gest@equipe.test')->get($this->host . "/recours/{$claim->id}")->assertOk()->assertSee('Paludisme grave');
    as_('tres@equipe.test')->get($this->host . "/recours/{$claim->id}")->assertOk()->assertDontSee('Paludisme')->assertSee('Vue « montants »', false);
    as_('tres@equipe.test')->get($this->host . '/recours')->assertOk()->assertDontSee('Paludisme');
});

it('ne laisse personne valider sa propre saisie, administrateur compris', function () {
    staffUser('boss@equipe.test', ['mutual_admin', 'benefits_manager']);     // cumul permis, signalé
    staffUser('ctrl@equipe.test', ['controller_validator']);
    $claim = $this->mutual->run(fn () => CareClaim::create(['member_id' => $this->member->id, 'opened_on' => now()->toDateString(), 'status' => 'open']));
    $base = $this->host . "/recours/{$claim->id}";
    as_('boss@equipe.test')->post($base . '/prestations', ['act_type_id' => $this->act->id, 'provider_id' => 999, 'care_date' => now()->toDateString(),
        'total' => '10000', 'channel' => 'tiers_payant'])->assertRedirect();
    $p = $this->mutual->run(fn () => Prestation::firstOrFail());
    expect($p->captured_by)->toBe(tenantUser('boss@equipe.test')->id)->and($p->source)->toBe('mutual')
        ->and($p->mutual_part_minor)->toBe(8000)->and($p->beneficiary_part_minor)->toBe(2000);

    as_('boss@equipe.test')->post($base . "/prestations/{$p->id}/validation")->assertSessionHas('error');
    as_('boss@equipe.test')->post($base . "/prestations/{$p->id}/rejet")->assertSessionHas('error');
    expect($this->mutual->run(fn () => $p->fresh()->status))->toBe('captured');
    as_('boss@equipe.test')->get($base)->assertSee('Votre saisie : à valider par une autre personne');
    asAdmin()->get($this->host . '/equipe')->assertSee('Cumul de rôles : ne valide jamais ses propres saisies.');

    as_('ctrl@equipe.test')->post($base . "/prestations/{$p->id}/validation")->assertSessionHas('success');
    $p = $this->mutual->run(fn () => $p->fresh());
    expect($p->status)->toBe('validated')->and($p->validated_by)->toBe(tenantUser('ctrl@equipe.test')->id);
});

it('applique les nouvelles incompatibilités et les rôles exclusifs', function () {
    $post = fn (array $roles, array $extra = []) => asAdmin()->post($this->host . '/equipe', ['first_name' => 'X', 'last_name' => 'Y',
        'email' => 'x' . random_int(1000, 9999) . '@equipe.test', 'roles' => $roles] + $extra);
    $post(['controller_validator', 'treasurer_accountant'])->assertSessionHasErrors('roles');
    $post(['benefits_manager', 'treasurer_accountant'])->assertSessionHasErrors('roles');
    $post(['auditor', 'community_manager'])->assertSessionHasErrors('roles');
    $post(['provider', 'enrollment_agent'])->assertSessionHasErrors('roles');
    $post(['provider'])->assertSessionHasErrors('care_provider_id');            // prestataire non rattaché
    $post(['auditor'])->assertSessionHasNoErrors();
    expect($this->mutual->run(fn () => User::role('auditor')->count()))->toBe(1);
    asAdmin()->get($this->host . '/equipe/roles-et-droits')->assertOk()->assertSee('Auditeur')
        ->assertSee('Contrôleur-validateur ≠ Trésorier-comptable', false);
});

it('exige la double authentification de tout le personnel ; tout le monde peut signaler un incident', function () {
    $agent = staffUser('agent@equipe.test', ['enrollment_agent'], ['two_factor_confirmed_at' => null]);
    $this->actingAs($agent, 'web')->get($this->host . '/adherents')->assertRedirect($this->host . '/mfa/activation');

    staffUser('cm@equipe.test', ['community_manager']);
    as_('cm@equipe.test')->from($this->host . '/signaler')->post($this->host . '/incidents', ['title' => 'Reçu imprimé deux fois', 'category' => 'operational',
        'severity' => 'low', 'occurred_on' => now()->toDateString(), 'via' => 'signal'])->assertRedirect($this->host . '/signaler');
    $i = $this->mutual->run(fn () => Incident::firstOrFail());
    expect($i->description)->toContain('Signalé par Cm Test');
    as_('cm@equipe.test')->get($this->host . '/incidents')->assertForbidden();
    as_('cm@equipe.test')->post($this->host . "/incidents/{$i->id}/statut", ['status' => 'closed'])->assertForbidden();
});

it('portail prestataire : vérifier, déclarer, cloisonner par établissement, faire valider puis facturer', function () {
    [$convA, $provA] = providerAccount('accueil@csi-a.test', 'CSI de Nkolbisson');
    [$convB, $provB] = providerAccount('accueil@csi-b.test', 'Clinique B');
    staffUser('ctrl@equipe.test', ['controller_validator']);
    staffUser('tres@equipe.test', ['treasurer_accountant']);
    $h = $this->host;

    // Accueil du prestataire = son portail ; le reste du back-office lui est fermé.
    expect(TenantAccess::home($provA))->toBe($h . '/prestataire');
    as_('accueil@csi-a.test')->get($h . '/prestataire')->assertOk()->assertSee('CSI de Nkolbisson');
    as_('accueil@csi-a.test')->get($h . '/adherents')->assertForbidden();

    // Vérification : code inconnu puis code valide (verdict seulement, journalisé).
    as_('accueil@csi-a.test')->post($h . '/prestataire/verifier', ['code' => 'MUT-NOPE'])->assertOk()->assertSee('Aucun adhérent avec ce code');
    as_('accueil@csi-a.test')->post($h . '/prestataire/verifier', ['code' => 'MUT-000777'])->assertOk()->assertSee('Droits ouverts')->assertSee('Nkolo Awa');
    $check = $this->mutual->run(fn () => RightsCheck::where('ok', true)->firstOrFail());
    expect($check->care_provider_id)->toBe($convA->care_provider_id);

    // Un autre établissement ne peut pas réutiliser cette vérification.
    as_('accueil@csi-b.test')->get($h . "/prestataire/declarer/{$check->id}")->assertNotFound();

    // Déclaration : 10 000 → part mutuelle 8 000, part patient 2 000.
    as_('accueil@csi-a.test')->get($h . "/prestataire/declarer/{$check->id}")->assertOk()->assertSee('Consultation de médecine générale');
    as_('accueil@csi-a.test')->post($h . "/prestataire/declarer/{$check->id}", ['beneficiary' => 'member', 'act_type_id' => $this->act->id,
        'care_date' => now()->toDateString(), 'total' => '10 000'])->assertRedirect($h . '/prestataire/prises-en-charge');
    $p = $this->mutual->run(fn () => Prestation::firstOrFail());
    expect($p->source)->toBe('provider')->and($p->provider_id)->toBe($convA->care_provider_id)->and($p->captured_by)->toBe($provA->id)
        ->and($p->mutual_part_minor)->toBe(8000)->and($p->status)->toBe('captured');

    // Cloisonnement : B ne voit rien de A ; le prestataire n'ouvre pas le recours côté mutuelle.
    as_('accueil@csi-a.test')->get($h . '/prestataire/prises-en-charge')->assertSee('Nkolo')->assertSee('En attente de validation');
    as_('accueil@csi-b.test')->get($h . '/prestataire/prises-en-charge')->assertDontSee('Nkolo');
    as_('accueil@csi-a.test')->get($h . "/recours/{$p->care_claim_id}")->assertForbidden();

    // Rien à facturer avant validation ; le contrôleur valide.
    as_('accueil@csi-a.test')->post($h . "/prestataire/factures/{$p->care_claim_id}")->assertStatus(422);
    as_('ctrl@equipe.test')->post($h . "/recours/{$p->care_claim_id}/prestations/{$p->id}/validation")->assertSessionHas('success');

    // Demande de paiement (une seule fois), puis paiement par le trésorier (hors plateforme, enregistré).
    as_('accueil@csi-a.test')->get($h . '/prestataire/factures')->assertSee('Demander le paiement');
    as_('accueil@csi-b.test')->post($h . "/prestataire/factures/{$p->care_claim_id}")->assertStatus(422);
    as_('accueil@csi-a.test')->post($h . "/prestataire/factures/{$p->care_claim_id}", ['provider_reference' => 'F-2026-014'])->assertSessionHas('success');
    as_('accueil@csi-a.test')->post($h . "/prestataire/factures/{$p->care_claim_id}")->assertStatus(422);
    $inv = $this->mutual->run(fn () => ProviderInvoice::firstOrFail());
    expect($inv->amount_minor)->toBe(8000)->and($inv->provider_id)->toBe($convA->care_provider_id)->and($inv->provider_reference)->toBe('F-2026-014');
    as_('tres@equipe.test')->post($h . "/recours/{$p->care_claim_id}/facture/{$inv->id}/paiement")->assertSessionHas('success');
    expect($this->mutual->run(fn () => [$inv->fresh()->status, TreasuryMovement::where('linked_reference', 'INV-' . $inv->id)->exists()]))->toBe(['paid', true]);
    as_('accueil@csi-a.test')->get($h . '/prestataire/factures')->assertSee('Payée')->assertSee('F-2026-014');

    // Convention suspendue : plus de vérification ni de déclaration ; l'historique reste consultable.
    asAdmin()->post($h . "/parametres/prestataires/{$convA->id}/activation")->assertRedirect();
    as_('accueil@csi-a.test')->post($h . '/prestataire/verifier', ['code' => 'MUT-000777'])->assertForbidden();
    as_('accueil@csi-a.test')->get($h . '/prestataire/prises-en-charge')->assertOk()->assertSee('n’est pas en vigueur', false);
});

it('propose la déclaration après le scan de la carte par un prestataire connecté', function () {
    [$conv] = providerAccount('accueil@csi-a.test', 'CSI de Nkolbisson');
    $token = $this->mutual->run(fn () => app(\App\Services\DigitalCardService::class)->issueToken($this->member));
    as_('accueil@csi-a.test')->get($this->host . '/carte/verification?t=' . urlencode($token))->assertOk()->assertSee('Déclarer une prise en charge');
    expect($this->mutual->run(fn () => RightsCheck::where('ok', true)->count()))->toBe(1);
    // Le même lien collé dans le portail fonctionne aussi.
    as_('accueil@csi-a.test')->post($this->host . '/prestataire/verifier', ['code' => $this->host . '/carte/verification?t=' . urlencode($token)])->assertSee('Droits ouverts');
});
