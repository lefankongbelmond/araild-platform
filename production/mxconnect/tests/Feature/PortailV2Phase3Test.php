<?php

/*
 * Portail prestataire V2 — phase 3 (2026-11-17) : identification et vérification des droits.
 * VRAIE base de mutuelle (mx_tests_tenant, ou tables préfixées avec MX_TEST_STORAGE=prefix).
 */

use App\Models\Tenant\ActType;
use App\Models\Tenant\Alert;
use App\Models\Tenant\BeneficiarySearch;
use App\Models\Tenant\Ceiling;
use App\Models\Tenant\ContributionSchedule;
use App\Models\Tenant\Dependent;
use App\Models\Tenant\Guarantee;
use App\Models\Tenant\GuaranteeVersion;
use App\Models\Tenant\Member;
use App\Models\Tenant\Prestation;
use App\Models\Tenant\RightsCheck;
use App\Models\Tenant\Subscription;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    provisionTestMutual();
    $this->c = rtrim((string) config('app.url'), '/');
    [$this->member, $this->act, $this->radio, $this->version, $this->child, $this->sub] = $this->mutual->run(function () {
        $g = Guarantee::create(['code' => 'ESS', 'name' => 'Essentielle', 'status' => 'active']);
        $cons = ActType::create(['code' => 'CONS', 'label' => 'Consultation de médecine générale', 'category' => 'consultation', 'tariff_minor' => 10000]);
        $radio = ActType::create(['code' => 'RADIO', 'label' => 'Radiographie thoracique', 'category' => 'imagerie']);
        $v = GuaranteeVersion::create(['guarantee_id' => $g->id, 'version_no' => 1, 'valid_from' => '2026-01-01', 'base_contribution_minor' => 1000,
            'periodicity' => 'monthly', 'coverage_rate' => 80, 'copay_rate' => 20, 'observation_days' => 0, 'exclusions' => [$radio->id]]);
        Ceiling::create(['guarantee_version_id' => $v->id, 'act_type_id' => null, 'ceiling_minor' => 20000, 'period' => 'annual']);
        $m = Member::create(['member_code' => 'MUT-000777', 'first_name' => 'Awa', 'last_name' => 'Ngono', 'birth_date' => '1990-04-02',
            'sex' => 'F', 'status' => 'active', 'joined_at' => '2026-01-01', 'phone' => '+237 699 11 22 33']);
        $sub = Subscription::create(['member_id' => $m->id, 'guarantee_version_id' => $v->id, 'contribution_minor' => 1000, 'effective_date' => '2026-01-01',
            'observation_ends_on' => '2026-01-01', 'status' => 'validated', 'captured_by' => 1]);
        ContributionSchedule::create(['subscription_id' => $sub->id, 'period' => now()->subMonth()->format('Y-m'), 'year' => now()->year, 'due_minor' => 1000,
            'due_date' => now()->subMonth()->startOfMonth()->addDays(4)->toDateString(), 'status' => 'paid']);
        ContributionSchedule::create(['subscription_id' => $sub->id, 'period' => now()->addMonth()->format('Y-m'), 'year' => now()->addMonth()->year, 'due_minor' => 1000,
            'due_date' => now()->addMonth()->startOfMonth()->addDays(4)->toDateString(), 'status' => 'to_pay']);
        $child = Dependent::create(['member_id' => $m->id, 'first_name' => 'Junior', 'last_name' => 'Ngono', 'birth_date' => '2015-06-01', 'sex' => 'M', 'relationship' => 'child', 'status' => 'active']);
        Dependent::create(['member_id' => $m->id, 'first_name' => 'Paul', 'last_name' => 'Ngono', 'birth_date' => '1988-01-10', 'sex' => 'M', 'relationship' => 'spouse', 'status' => 'removed']);

        return [$m, $cons, $radio, $v, $child, $sub];
    });
    $this->conv = conventioned();
    // Convention créée par la mutuelle : indexée « en place » dans l'espace central (correctif 2026-11-17).
    tenancy()->end();
    expect(\App\Models\Provider\ProviderAffiliation::where('care_provider_id', $this->conv->care_provider_id)->value('status'))->toBe('approved');
    $this->prov = staffUser('accueil@csi.test', ['provider'], ['care_provider_id' => $this->conv->care_provider_id]);
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

function p3Verify(string $code = 'MUT-000777')
{
    return as_('accueil@csi.test')->post(test()->host . '/prestataire/verifier', ['code' => $code]);
}

it('affiche le statut des droits, l’identité et la situation mutualiste issue des paramètres de la garantie', function () {
    p3Verify()->assertOk()->assertSee('DROITS ACTIFS')->assertSee('NGONO Awa')->assertSee('Adhérent principal')->assertSee('Mutuelle Équipe', false)
        ->assertSee('Essentielle')->assertSee('80 %')->assertSee('20 %')->assertSee('À jour')->assertSee('Tous actes')->assertSee('reste')
        ->assertSee('Radiographie thoracique')->assertSee('Vérifié le');
    expect($this->mutual->run(fn () => RightsCheck::latest('id')->value('method')))->toBe('code');

    // 2e vérification : la précédente est rappelée.
    p3Verify()->assertSee('vérification précédente par votre établissement', false);

    // Cotisation impayée et échue : droits suspendus.
    $this->mutual->run(fn () => ContributionSchedule::create(['subscription_id' => $this->sub->id, 'period' => now()->subMonths(2)->format('Y-m'), 'year' => now()->year,
        'due_minor' => 1000, 'due_date' => now()->subDays(40)->toDateString(), 'status' => 'to_pay']));
    p3Verify()->assertSee('DROITS SUSPENDUS')->assertSee('En retard : 1 échéance(s)', false);
    $this->mutual->run(fn () => ContributionSchedule::where('status', 'to_pay')->whereDate('due_date', '<', now()->toDateString())->update(['status' => 'paid']));

    // Adhérent sorti : droits expirés ; code inconnu : non identifié.
    $this->mutual->run(fn () => Member::whereKey($this->member->id)->update(['status' => 'left']));
    p3Verify()->assertSee('DROITS EXPIRÉS');
    $this->mutual->run(fn () => Member::whereKey($this->member->id)->update(['status' => 'active']));
    p3Verify('MUT-999999')->assertSee('BÉNÉFICIAIRE NON IDENTIFIÉ');

    // Établissement hors réseau de la garantie.
    $this->mutual->run(fn () => GuaranteeVersion::whereKey($this->version->id)->update(['provider_network' => json_encode([999999])]));
    p3Verify()->assertSee('HORS RÉSEAU')->assertSee('Hors réseau de la garantie');
    $this->mutual->run(fn () => GuaranteeVersion::whereKey($this->version->id)->update(['provider_network' => null]));

    // Adhésion pas encore validée : en attente de confirmation.
    $this->mutual->run(function () {
        $m = Member::create(['member_code' => 'MUT-000778', 'first_name' => 'Eric', 'last_name' => 'Mvondo', 'birth_date' => '1980-01-01', 'sex' => 'M', 'status' => 'active', 'joined_at' => now()->toDateString()]);
        Subscription::create(['member_id' => $m->id, 'guarantee_version_id' => $this->version->id, 'contribution_minor' => 1000, 'effective_date' => now()->toDateString(), 'status' => 'captured', 'captured_by' => 1]);
    });
    p3Verify('MUT-000778')->assertSee('DROITS EN ATTENTE DE CONFIRMATION')->assertSee('Adhésion en attente de validation');
});

it('recherche un bénéficiaire sans carte avec deux critères, journalise et protège le choix', function () {
    $h = $this->host;
    as_('accueil@csi.test')->post("$h/prestataire/rechercher", ['mode' => 'name', 'last_name' => 'Ngono'])->assertSessionHasErrors('birth_date');
    as_('accueil@csi.test')->post("$h/prestataire/rechercher", ['mode' => 'phone', 'phone' => '699 11 22 33'])->assertSessionHasErrors('birth_year');

    // Nom + date de naissance : l'ayant droit est trouvé, code masqué.
    $page = as_('accueil@csi.test')->post("$h/prestataire/rechercher", ['mode' => 'name', 'last_name' => 'ngono', 'birth_date' => '2015-06-01'])
        ->assertOk()->assertSee('NGONO Junior')->assertSee('Ayant droit (enfant)')->assertSee('•••••••777')->assertDontSee('MUT-000777');
    preg_match('/name="pick" value="([^"]+)"/', $page->getContent(), $mm);
    expect($mm[1] ?? null)->not->toBeNull();

    // Vérifier ses droits : vérification au nom de l'ayant droit, méthode « search ».
    as_('accueil@csi.test')->post("$h/prestataire/rechercher/choix", ['pick' => html_entity_decode($mm[1])])->assertOk()
        ->assertSee('DROITS ACTIFS')->assertSee('Ayant droit (enfant) de NGONO Awa', false);
    $check = $this->mutual->run(fn () => RightsCheck::latest('id')->firstOrFail());
    expect($check->dependent_id)->toBe($this->child->id)->and($check->method)->toBe('search');
    // La déclaration propose directement cet ayant droit.
    as_('accueil@csi.test')->get("$h/prestataire/declarer/{$check->id}")->assertSee('value="' . $this->child->id . '" selected', false);

    // Un jeton de choix ne sert qu'à la personne qui a fait la recherche.
    staffUser('autre@csi.test', ['provider'], ['care_provider_id' => $this->conv->care_provider_id]);
    as_('autre@csi.test')->post("$h/prestataire/rechercher/choix", ['pick' => html_entity_decode($mm[1])])->assertStatus(410);
    as_('accueil@csi.test')->post("$h/prestataire/rechercher/choix", ['pick' => 'falsifie'])->assertStatus(410);

    // Téléphone + année de naissance ; ayant droit retiré : droits expirés ; aucun résultat : non identifié.
    as_('accueil@csi.test')->post("$h/prestataire/rechercher", ['mode' => 'phone', 'phone' => '+237699112233', 'birth_year' => 1990])->assertSee('NGONO Awa');
    $page = as_('accueil@csi.test')->post("$h/prestataire/rechercher", ['mode' => 'name', 'last_name' => 'Ngono', 'first_name' => 'Pa', 'birth_date' => '1988-01-10'])->assertSee('NGONO Paul');
    preg_match('/name="pick" value="([^"]+)"/', $page->getContent(), $mm);
    as_('accueil@csi.test')->post("$h/prestataire/rechercher/choix", ['pick' => html_entity_decode($mm[1])])->assertSee('DROITS EXPIRÉS')->assertSee('Ayant droit retiré');
    as_('accueil@csi.test')->post("$h/prestataire/rechercher", ['mode' => 'phone', 'phone' => '699112233', 'birth_year' => 1975])->assertSee('BÉNÉFICIAIRE NON IDENTIFIÉ');

    $log = $this->mutual->run(fn () => BeneficiarySearch::orderBy('id')->get());
    expect($log)->toHaveCount(4)->and($log[0]->criteria['birth_date'])->toBe('2015-06-01')->and($log[0]->results)->toBe(1)->and($log[3]->results)->toBe(0)
        ->and($log[0]->user_id)->toBe($this->prov->id);
});

it('estime la prise en charge avant soins à partir des paramètres de la garantie, sans rien enregistrer', function () {
    p3Verify();
    $check = $this->mutual->run(fn () => RightsCheck::where('ok', true)->latest('id')->firstOrFail());
    $url = $this->host . "/prestataire/estimer/{$check->id}";

    // 15 000 : 80 % = 12 000 (plafond annuel 20 000 intact) ; 3 000 pour le patient ; au-dessus du tarif de 10 000.
    as_('accueil@csi.test')->post($url, ['act_type_id' => $this->act->id, 'total' => '15 000'])->assertOk()
        ->assertSee('Estimation')->assertSeeInOrder(['Part de la mutuelle', '12 000'])->assertSeeInOrder(['À payer par le patient', '3 000'])
        ->assertSee('dépasse le tarif conventionnel', false)->assertSee('n’est ni un accord de prise en charge', false);

    // Acte exclu : tout à la charge du patient.
    as_('accueil@csi.test')->post($url, ['act_type_id' => $this->radio->id, 'total' => '20 000'])->assertSee('Acte non couvert par la garantie');

    // Plafond déjà consommé à 12 000 : il reste 8 000 ; 4 000 au-delà du plafond, non couverts.
    $this->mutual->run(function () {
        $claim = \App\Models\Tenant\CareClaim::create(['member_id' => $this->member->id, 'opened_on' => now()->toDateString(), 'status' => 'open']);
        $claim->prestations()->create(['act_type_id' => $this->act->id, 'provider_id' => 999, 'guarantee_version_id' => $this->version->id,
            'care_date' => now()->toDateString(), 'total_minor' => 15000, 'mutual_part_minor' => 12000, 'beneficiary_part_minor' => 3000, 'status' => 'validated']);
    });
    $res = as_('accueil@csi.test')->post($url, ['act_type_id' => $this->act->id, 'total' => '15 000'])->assertOk();
    $res->assertSeeInOrder(['Part de la mutuelle', '8 000'])->assertSeeInOrder(['au-delà du plafond', '4 000'])->assertSeeInOrder(['Plafond restant avant ces soins', '8 000', '0']);
    // La prestation d'un AUTRE établissement n'apparaît pas dans l'historique (seulement le plafond, consommé).
    $res->assertSee('Aucune prise en charge de votre établissement sur 12 mois.');
    expect($this->mutual->run(fn () => Prestation::count()))->toBe(1);
});

it('signale (sans bloquer) un montant déclaré au-dessus du tarif conventionnel', function () {
    p3Verify();
    $check = $this->mutual->run(fn () => RightsCheck::where('ok', true)->latest('id')->firstOrFail());
    as_('accueil@csi.test')->post($this->host . "/prestataire/declarer/{$check->id}", ['beneficiary' => 'member', 'act_type_id' => $this->act->id,
        'care_date' => now()->toDateString(), 'total' => '15 000'])->assertRedirect($this->host . '/prestataire/prises-en-charge');
    $codes = $this->mutual->run(fn () => Alert::pluck('details')->map(fn ($d) => $d['code'] ?? null)->all());
    expect($codes)->toContain('above_tariff');
    // Historique de l'établissement : la prise en charge apparaît maintenant.
    p3Verify()->assertSee('Prises en charge de votre établissement pour ce bénéficiaire')->assertSee('Consultation de médecine générale');
});

it('recherche une carte dans les mutuelles conventionnées depuis l’espace central et ouvre le bon portail, code pré-rempli', function () {
    $nurse = proAccount('inf@csi.test', $this->conv->care_provider_id, 'nurse');

    // Code connu : redirection vers l'entrée du portail de la mutuelle, code transmis.
    $res = asPro($nurse)->post($this->c . '/espace-prestataire/rechercher', ['code' => 'MUT-000777']);
    $this->get(entryUrl($res))->assertRedirect();
    $user = $this->mutual->run(fn () => \App\Models\Tenant\User::where('provider_account_id', $nurse->id)->firstOrFail());
    as_($user->email)->withSession(['portal_prefill' => 'MUT-000777'])->get($this->host . '/prestataire')
        ->assertSee('Code transmis depuis votre espace prestataire', false)->assertSee('value="MUT-000777"', false);

    // QR de la carte : la mutuelle est lue dans le jeton.
    $token = $this->mutual->run(fn () => app(\App\Services\DigitalCardService::class)->issueToken($this->member));
    tenancy()->end();
    $res = asPro($nurse)->post($this->c . '/espace-prestataire/rechercher', ['code' => $this->host . '/carte/verification?t=' . urlencode($token)]);
    entryUrl($res);

    // Inconnu : non identifié, journalisé par empreinte (jamais le code en clair).
    tenancy()->end();
    asPro($nurse)->post($this->c . '/espace-prestataire/rechercher', ['code' => 'MUT-424242'])->assertOk()->assertSee('BÉNÉFICIAIRE NON IDENTIFIÉ');
    $log = DB::connection('central')->table('provider_lookups')->orderBy('id')->get();
    expect($log)->toHaveCount(3)->and($log[0]->matches)->toBe(1)->and($log[1]->mode)->toBe('qr')->and($log[2]->matches)->toBe(0)
        ->and($log[2]->code_hash)->toBe(hash('sha256', 'mut-424242'))->and(json_encode($log))->not->toContain('MUT-424242');

    // Agent de facturation : pas de vérification des droits.
    $bill = proAccount('fact@csi.test', $this->conv->care_provider_id, 'billing');
    asPro($bill)->post($this->c . '/espace-prestataire/rechercher', ['code' => 'MUT-000777'])->assertForbidden();
});
