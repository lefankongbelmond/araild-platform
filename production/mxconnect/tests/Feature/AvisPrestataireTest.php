<?php

/*
 * Portail prestataire V2, phase 6 (2026-11-25) : avis des mutuelles aux établissements.
 * Décisions de la mutuelle → avis dans l'espace central, filtrés par les droits de la personne
 * dans l'établissement, jamais visibles d'un autre établissement, sans donnée médicale.
 */

use App\Models\Provider\ProviderNotification;
use App\Models\Tenant\ActType;
use App\Models\Tenant\CareClaim;
use App\Models\Tenant\Guarantee;
use App\Models\Tenant\GuaranteeVersion;
use App\Models\Tenant\Member;
use App\Models\Tenant\Preauthorization;
use App\Models\Tenant\Prestation;
use App\Models\Tenant\ProviderInvoice;
use App\Models\Tenant\Subscription;
use App\Services\Tenant\PreauthorizationService;
use App\Support\ProviderNotifier;

beforeEach(function () {
    provisionTestMutual();
    $this->c = rtrim((string) config('app.url'), '/');
    staffUser('saisie@equipe.test', ['benefits_manager']);
    staffUser('tresor@equipe.test', ['treasurer_accountant']);
    $this->conv = conventioned('CSI Akono');
    $this->ids = $this->mutual->run(function () {
        $g = Guarantee::create(['code' => 'ESS', 'name' => 'Essentielle', 'status' => 'active']);
        $v = GuaranteeVersion::create(['guarantee_id' => $g->id, 'version_no' => 1, 'valid_from' => '2026-01-01', 'base_contribution_minor' => 1000,
            'periodicity' => 'monthly', 'coverage_rate' => 80, 'copay_rate' => 20, 'observation_days' => 0]);
        $m = Member::create(['member_code' => 'MUT-AVIS-01', 'first_name' => 'Awa', 'last_name' => 'Ngono', 'birth_date' => '1990-04-02', 'sex' => 'F', 'status' => 'active', 'joined_at' => '2026-01-01']);
        Subscription::create(['member_id' => $m->id, 'guarantee_version_id' => $v->id, 'contribution_minor' => 1000, 'effective_date' => '2026-01-01',
            'observation_ends_on' => '2026-01-01', 'status' => 'validated', 'captured_by' => 1]);
        $act = ActType::create(['code' => 'CONS', 'label' => 'Consultation de médecine générale', 'category' => 'consultation']);
        $by = \App\Models\Tenant\User::where('email', 'saisie@equipe.test')->value('id');
        $claim = CareClaim::create(['member_id' => $m->id, 'opened_on' => now()->toDateString(), 'status' => 'open']);
        $mk = fn () => Prestation::create(['care_claim_id' => $claim->id, 'act_type_id' => $act->id, 'provider_id' => $this->conv->care_provider_id,
            'guarantee_version_id' => $v->id, 'care_date' => now()->toDateString(), 'total_minor' => 10000, 'mutual_part_minor' => 8000,
            'beneficiary_part_minor' => 2000, 'status' => 'captured', 'captured_by' => $by]);
        $p1 = $mk();
        $p2 = $mk();
        $inv = ProviderInvoice::create(['provider_id' => $this->conv->care_provider_id, 'care_claim_id' => $claim->id, 'amount_minor' => 8000, 'status' => 'prepared', 'captured_by' => $by]);
        $inv2 = ProviderInvoice::create(['provider_id' => $this->conv->care_provider_id, 'care_claim_id' => $claim->id, 'amount_minor' => 3000, 'status' => 'prepared', 'captured_by' => $by]);
        $pa = Preauthorization::create(['member_id' => $m->id, 'provider_id' => $this->conv->care_provider_id, 'act_type_id' => $act->id,
            'planned_on' => now()->addDays(3)->toDateString(), 'estimated_minor' => 50000, 'status' => 'requested', 'review_type' => 'administrative', 'requested_by' => $by,
            'clinical_info' => 'Information clinique confidentielle']);

        return ['claim' => $claim->id, 'p1' => $p1->id, 'p2' => $p2->id, 'inv' => $inv->id, 'inv2' => $inv2->id, 'pa' => $pa->id];
    });
    tenancy()->end();
    $this->admin = proAccount('direction@akono.test', $this->conv->care_provider_id, 'admin');
    $this->doctor = proAccount('docteur@akono.test', $this->conv->care_provider_id, 'doctor');
    $autre = \App\Models\Central\CareProvider::create(['name' => 'Centre Ailleurs', 'type' => 'health_center', 'locality' => 'Douala', 'active' => true,
        'country_id' => \App\Models\Central\Country::where('active', true)->value('id')]);
    $this->other = proAccount('autre@ailleurs.test', $autre->id, 'admin');
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('prévient l’établissement de chaque décision de la mutuelle, sans donnée médicale', function () {
    $h = $this->host;
    $i = $this->ids;
    asAdmin()->post($h . "/recours/{$i['claim']}/prestations/{$i['p1']}/validation")->assertRedirect();
    asAdmin()->post($h . "/recours/{$i['claim']}/prestations/{$i['p2']}/rejet", ['note' => 'Acte déjà facturé le même jour'])->assertRedirect();
    as_('tresor@equipe.test')->post($h . "/recours/{$i['claim']}/facture/{$i['inv']}/paiement", ['mode' => 'bank'])->assertRedirect();
    asAdmin()->post($h . "/factures-prestataires/{$i['inv2']}/rejet", ['note' => 'Pièce justificative illisible'])->assertRedirect();
    $this->mutual->run(fn () => app(PreauthorizationService::class)->decide(Preauthorization::find($i['pa']), \App\Models\Tenant\User::where('email', 'admin@equipe.test')->first(), 'approved', null, 45000));
    tenancy()->end();

    $all = ProviderNotification::where('care_provider_id', $this->conv->care_provider_id)->orderBy('id')->get();
    expect($all->pluck('kind')->all())->toBe(['claim_validated', 'claim_rejected', 'invoice_paid', 'invoice_rejected', 'preauth_approved']);
    expect($all[0]->title)->toContain('Mutuelle Équipe')->toContain('validée');
    expect($all[1]->body)->toContain('Acte déjà facturé le même jour')->toContain('MUT-AVIS-01');
    expect($all[4]->title)->toContain('AUT-');
    // Jamais de donnée médicale : ni l'acte, ni l'information clinique.
    foreach ($all as $n) {
        expect($n->title . ' ' . $n->body)->not->toContain('Consultation')->not->toContain('clinique');
    }

    // L'administrateur de l'établissement voit tout ; le médecin ne voit pas les factures ; l'autre établissement ne voit rien.
    $m = fn ($a) => \App\Models\Provider\ProviderMembership::where('provider_account_id', $a->id)->first();
    expect(ProviderNotifier::unread($m($this->admin)))->toBe(5)->and(ProviderNotifier::unread($m($this->doctor)))->toBe(3)
        ->and(ProviderNotifier::unread($m($this->other)))->toBe(0);

    asPro($this->doctor)->get($this->c . '/espace-prestataire')->assertSee('3 non lu(s)', false);
    asPro($this->doctor)->get($this->c . '/espace-prestataire/avis')->assertOk()
        ->assertSee('Prise en charge n° ' . $i['p1'] . ' validée')->assertSee('Accord préalable accordé')->assertDontSee('Facture n°')->assertSee('Nouveau');
    expect(ProviderNotifier::unread($m($this->doctor)->fresh()))->toBe(0)->and(ProviderNotifier::unread($m($this->admin)))->toBe(5);   // lu pour lui seul
    asPro($this->admin)->get($this->c . '/espace-prestataire/avis')->assertSee('Facture n° ' . $i['inv'] . ' payée')->assertSee('Pièce justificative illisible');
    asPro($this->other)->get($this->c . '/espace-prestataire/avis')->assertOk()->assertSee('Aucun avis');
});

it('prévient de la décision sur une demande d’affiliation', function () {
    tenancy()->end();
    $other = \App\Models\Central\CareProvider::create(['name' => 'Clinique Espoir', 'type' => 'clinic', 'country_id' => \App\Models\Central\Country::where('active', true)->value('id'),
        'locality' => 'Yaoundé', 'active' => true]);
    $aff = \App\Models\Provider\ProviderAffiliation::create(['care_provider_id' => $other->id, 'mutual_id' => $this->mutual->id, 'status' => 'requested']);
    asAdmin()->post($this->host . "/parametres/prestataires/demandes/{$aff->id}/refuser", ['note' => 'Zone non couverte pour le moment'])->assertRedirect();
    tenancy()->end();
    $n = ProviderNotification::where('care_provider_id', $other->id)->first();
    expect($n->kind)->toBe('affiliation_rejected')->and($n->body)->toContain('Zone non couverte')->and($n->permission)->toBe('affiliation.request');
});
