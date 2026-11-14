<?php

/*
 * Portail prestataire V2 — phase 1 (2026-11-15) : corrections de l'existant.
 * Testé dans une VRAIE base de mutuelle (mx_tests_tenant, provisionnée comme sur LWS).
 */

use App\Models\Central\CareProvider;
use App\Models\Tenant\ActType;
use App\Models\Tenant\Alert;
use App\Models\Tenant\Ceiling;
use App\Models\Tenant\Guarantee;
use App\Models\Tenant\GuaranteeVersion;
use App\Models\Tenant\Member;
use App\Models\Tenant\Prestation;
use App\Models\Tenant\ProviderConvention;
use App\Models\Tenant\ProviderInvoice;
use App\Models\Tenant\RightsCheck;
use App\Models\Tenant\Subscription;

beforeEach(function () {
    provisionTestMutual();
    [$this->member, $this->act, $this->version] = $this->mutual->run(function () {
        $g = Guarantee::create(['code' => 'ESS', 'name' => 'Essentielle', 'status' => 'active']);
        $v = GuaranteeVersion::create(['guarantee_id' => $g->id, 'version_no' => 1, 'valid_from' => '2026-01-01', 'base_contribution_minor' => 1000,
            'periodicity' => 'monthly', 'coverage_rate' => 80, 'copay_rate' => 20, 'observation_days' => 0]);
        $m = Member::create(['member_code' => 'MUT-000888', 'first_name' => 'Paul', 'last_name' => 'Essomba', 'birth_date' => '1985-02-11',
            'sex' => 'M', 'status' => 'active', 'joined_at' => '2026-01-01']);
        Subscription::create(['member_id' => $m->id, 'guarantee_version_id' => $v->id, 'contribution_minor' => 1000, 'effective_date' => '2026-01-01',
            'observation_ends_on' => '2026-01-01', 'status' => 'validated', 'captured_by' => 1]);
        $act = ActType::create(['code' => 'CONS', 'label' => 'Consultation de médecine générale', 'category' => 'consultation']);

        return [$m, $act, $v];
    });
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

function v2Provider(string $email, string $name): array
{
    asAdmin()->post(test()->host . '/parametres/prestataires', ['mode' => 'new', 'name' => $name, 'type' => 'health_center', 'locality' => 'Yaoundé'])->assertRedirect();
    $conv = test()->mutual->run(fn () => ProviderConvention::latest('id')->firstOrFail());
    $user = staffUser($email, ['provider'], ['care_provider_id' => $conv->care_provider_id]);

    return [$conv, $user];
}

/** Vérifie les droits puis déclare ; renvoie la réponse de la déclaration. */
function v2Declare(string $email, int $actId, string $total, array $extra = [])
{
    as_($email)->post(test()->host . '/prestataire/verifier', ['code' => 'MUT-000888'])->assertSee('Droits ouverts');
    $check = test()->mutual->run(fn () => RightsCheck::where('ok', true)->latest('id')->firstOrFail());

    return as_($email)->post(test()->host . "/prestataire/declarer/{$check->id}", $extra + ['beneficiary' => 'member', 'act_type_id' => $actId,
        'care_date' => now()->toDateString(), 'total' => $total]);
}

it('sert le lecteur de QR du portail par l’application, sur l’adresse de la mutuelle', function () {
    v2Provider('accueil@csi.test', 'CSI Akono');
    $page = as_('accueil@csi.test')->get($this->host . '/prestataire')->assertOk()->getContent();
    expect($page)->toContain('/installer/fichiers/mx-scan.js')->toContain('/installer/fichiers/jsqr-1.4.0.min.js')
        ->not->toContain('src="/js/');
    $this->get($this->host . '/installer/fichiers/mx-scan.js')->assertOk()->assertHeader('Content-Type', 'text/javascript; charset=utf-8');
    $this->get($this->host . '/installer/fichiers/jsqr-1.4.0.min.js')->assertOk();
    $this->get($this->host . '/installer/fichiers/..%2F.env')->assertNotFound();
});

it('exige un motif pour rejeter une prestation et le montre au prestataire', function () {
    v2Provider('accueil@csi.test', 'CSI Akono');
    staffUser('ctrl@equipe.test', ['controller_validator']);
    v2Declare('accueil@csi.test', $this->act->id, '10 000')->assertRedirect();
    $p = $this->mutual->run(fn () => Prestation::firstOrFail());
    $url = $this->host . "/recours/{$p->care_claim_id}/prestations/{$p->id}/rejet";

    as_('ctrl@equipe.test')->get($this->host . "/recours/{$p->care_claim_id}")->assertSee('Motif du rejet');
    as_('ctrl@equipe.test')->post($url, ['note' => ''])->assertSessionHasErrors('note');
    expect($this->mutual->run(fn () => $p->fresh()->status))->toBe('captured');
    as_('ctrl@equipe.test')->post($url, ['note' => 'Acte déjà pris en charge par une autre garantie'])->assertSessionHas('success');
    $fresh = $this->mutual->run(fn () => $p->fresh());
    expect($fresh->status)->toBe('rejected')->and($fresh->decision_note)->toBe('Acte déjà pris en charge par une autre garantie')
        ->and($fresh->decided_at)->not->toBeNull();
    as_('accueil@csi.test')->get($this->host . '/prestataire/prises-en-charge?statut=rejected')
        ->assertSee('Rejetée')->assertSee('Motif : Acte déjà pris en charge par une autre garantie');
});

it('demande une confirmation avant une seconde déclaration identique, sans révéler celles des autres établissements', function () {
    v2Provider('accueil@csi.test', 'CSI Akono');
    v2Provider('accueil@clinique.test', 'Clinique Mvog-Ada');
    v2Declare('accueil@csi.test', $this->act->id, '10 000')->assertRedirect($this->host . '/prestataire/prises-en-charge');

    // Même établissement, même personne, même acte, même date : rien n'est enregistré sans confirmation.
    v2Declare('accueil@csi.test', $this->act->id, '10 000')->assertSessionHas('duplicate_of');
    expect($this->mutual->run(fn () => Prestation::count()))->toBe(1);
    // Confirmation explicite : acceptée, et la mutuelle reçoit un signal à examiner (pas un refus).
    v2Declare('accueil@csi.test', $this->act->id, '10 000', ['confirm_distinct' => '1'])->assertRedirect($this->host . '/prestataire/prises-en-charge');
    expect($this->mutual->run(fn () => [Prestation::count(), Alert::where('category', 'duplicate')->count() >= 1]))->toBe([2, true]);

    // Un autre établissement n'est pas averti de la prise en charge du premier.
    v2Declare('accueil@clinique.test', $this->act->id, '10 000')->assertSessionMissing('duplicate_of')->assertRedirect($this->host . '/prestataire/prises-en-charge');
});

it('ne crée qu’une seule demande de paiement, et laisse facturer après l’échéance de la convention mais pas après sa suspension', function () {
    [$conv] = v2Provider('accueil@csi.test', 'CSI Akono');
    staffUser('ctrl@equipe.test', ['controller_validator']);
    v2Declare('accueil@csi.test', $this->act->id, '10 000');
    v2Declare('accueil@csi.test', $this->act->id, '5 000', ['confirm_distinct' => '1']);
    [$p1, $p2] = $this->mutual->run(fn () => Prestation::orderBy('id')->get()->all());
    foreach ([$p1, $p2] as $p) {
        as_('ctrl@equipe.test')->post($this->host . "/recours/{$p->care_claim_id}/prestations/{$p->id}/validation")->assertSessionHas('success');
    }

    // Convention arrivée à échéance : plus de vérification, mais facturation des soins validés.
    $this->mutual->run(fn () => $conv->update(['ends_on' => now()->subDay()->toDateString()]));
    as_('accueil@csi.test')->post($this->host . '/prestataire/verifier', ['code' => 'MUT-000888'])->assertForbidden();
    as_('accueil@csi.test')->get($this->host . '/prestataire/factures')->assertSee('Demander le paiement')->assertSee('demander le paiement des soins déjà validés', false);
    as_('accueil@csi.test')->post($this->host . "/prestataire/factures/{$p1->care_claim_id}", ['provider_reference' => 'F-1'])->assertSessionHas('success');
    as_('accueil@csi.test')->post($this->host . "/prestataire/factures/{$p1->care_claim_id}", ['provider_reference' => 'F-1 bis'])->assertStatus(422);
    expect($this->mutual->run(fn () => ProviderInvoice::where('care_claim_id', $p1->care_claim_id)->count()))->toBe(1);

    // Convention suspendue par la mutuelle : facturation en ligne fermée, historique consultable.
    asAdmin()->post($this->host . "/parametres/prestataires/{$conv->id}/activation")->assertRedirect();
    as_('accueil@csi.test')->post($this->host . "/prestataire/factures/{$p2->care_claim_id}")->assertForbidden();
    as_('accueil@csi.test')->get($this->host . '/prestataire/factures')->assertOk()->assertDontSee('Demander le paiement')->assertSee('F-1');
});

it('ferme les actions d’un établissement désactivé dans le registre central', function () {
    [$conv] = v2Provider('accueil@csi.test', 'CSI Akono');
    tenancy()->end();
    CareProvider::on('central')->whereKey($conv->care_provider_id)->update(['active' => false]);
    as_('accueil@csi.test')->get($this->host . '/prestataire')->assertOk()->assertSee('n’est pas en vigueur', false);
    as_('accueil@csi.test')->post($this->host . '/prestataire/verifier', ['code' => 'MUT-000888'])->assertForbidden();
});

it('réserve le plafond annuel pour les prises en charge en attente de validation', function () {
    v2Provider('accueil@csi.test', 'CSI Akono');
    $this->mutual->run(fn () => Ceiling::create(['guarantee_version_id' => $this->version->id, 'act_type_id' => null, 'ceiling_minor' => 10000, 'period' => 'annual']));

    // 1re : 10 000 → 8 000 (80 %). 2e, encore en attente de la 1re : il ne reste que 2 000.
    v2Declare('accueil@csi.test', $this->act->id, '10 000');
    v2Declare('accueil@csi.test', $this->act->id, '10 000', ['confirm_distinct' => '1']);
    $parts = $this->mutual->run(fn () => Prestation::orderBy('id')->pluck('mutual_part_minor')->all());
    expect($parts)->toBe([8000, 2000]);
    expect($this->mutual->run(fn () => Prestation::orderBy('id')->get()->sum('mutual_part_minor')))->toBeLessThanOrEqual(10000);
});
