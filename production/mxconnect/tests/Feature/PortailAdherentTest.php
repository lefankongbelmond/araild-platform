<?php

/*
 * Mission « Mutuelle paramétrable », L4 (2026-11-20) : portail adhérent dans le compte MX-CONNECT.
 * Scénario 1 (l'adhérent consulte ses garanties) et scénario 9 (jamais les données d'une autre
 * mutuelle ou d'un autre adhérent). VRAIE base de mutuelle (ou tables préfixées).
 */

use App\Models\Central\MemberActivationCode;
use App\Models\Central\MemberLink;
use App\Models\Social\SocialNotification;
use App\Models\Tenant\ActType;
use App\Models\Tenant\CareClaim;
use App\Models\Tenant\Complaint;
use App\Models\Tenant\ContributionSchedule;
use App\Models\Tenant\CoverageLimit;
use App\Models\Tenant\CoverageRule;
use App\Models\Tenant\Dependent;
use App\Models\Tenant\Guarantee;
use App\Models\Tenant\GuaranteeVersion;
use App\Models\Tenant\Member;
use App\Models\Tenant\MemberChangeRequest;
use App\Models\Tenant\Prestation;
use App\Models\Tenant\ServiceCategory;
use App\Models\Tenant\Subscription;
use App\Models\Tenant\WaitingPeriod;

beforeEach(function () {
    provisionTestMutual();
    staffUser('agent@equipe.test', ['enrollment_agent']);
    staffUser('controle@equipe.test', ['controller_validator']);
    staffUser('tresor@equipe.test', ['treasurer_accountant']);
    $this->ids = $this->mutual->run(function () {
        $cat = fn ($c) => ServiceCategory::where('code', $c)->value('id');
        $g = Guarantee::create(['code' => 'FAM', 'name' => 'Famille', 'status' => 'active']);
        $v = GuaranteeVersion::create(['guarantee_id' => $g->id, 'version_no' => 1, 'valid_from' => now()->subYear()->toDateString(),
            'base_contribution_minor' => 5000, 'periodicity' => 'monthly', 'coverage_rate' => 0, 'copay_rate' => 0, 'observation_days' => 0]);
        $cons = ActType::create(['code' => 'CONS', 'label' => 'Consultation', 'category' => 'Consultations', 'service_category_id' => $cat('consultation')]);
        $rc = CoverageRule::create(['guarantee_version_id' => $v->id, 'service_category_id' => $cat('consultation'), 'label' => 'Consultations',
            'beneficiary_category' => 'all', 'coverage_rate' => 80, 'copay_rate' => 20]);
        $rh = CoverageRule::create(['guarantee_version_id' => $v->id, 'service_category_id' => $cat('hospitalization'), 'label' => 'Hospitalisation',
            'beneficiary_category' => 'all', 'coverage_rate' => 70, 'requires_authorization' => true]);
        CoverageLimit::create(['guarantee_version_id' => $v->id, 'label' => 'Plafond annuel familial', 'rule_keys' => null, 'scope' => 'year', 'holder' => 'family', 'amount_minor' => 100000]);
        WaitingPeriod::create(['guarantee_version_id' => $v->id, 'rule_key' => $rh->rule_key, 'beneficiary_category' => 'all', 'days' => 400]);
        $m = Member::create(['member_code' => 'MUT-000800', 'first_name' => 'Awa', 'last_name' => 'Ngono', 'birth_date' => '1990-04-02',
            'sex' => 'F', 'status' => 'active', 'joined_at' => now()->subYear()->toDateString(), 'phone' => '699112233']);
        $other = Member::create(['member_code' => 'MUT-000801', 'first_name' => 'Paul', 'last_name' => 'Abena', 'birth_date' => '1985-01-01',
            'sex' => 'M', 'status' => 'active', 'joined_at' => now()->subYear()->toDateString()]);
        $sub = Subscription::create(['member_id' => $m->id, 'guarantee_version_id' => $v->id, 'contribution_minor' => 5000,
            'effective_date' => now()->subYear()->toDateString(), 'status' => 'validated', 'captured_by' => 1]);
        Subscription::create(['member_id' => $other->id, 'guarantee_version_id' => $v->id, 'contribution_minor' => 5000,
            'effective_date' => now()->subYear()->toDateString(), 'status' => 'validated', 'captured_by' => 1]);
        $paid = ContributionSchedule::create(['subscription_id' => $sub->id, 'period' => now()->subMonth()->format('Y-m'), 'year' => now()->year,
            'due_minor' => 5000, 'due_date' => now()->subMonth()->toDateString(), 'status' => 'to_pay']);
        ContributionSchedule::create(['subscription_id' => $sub->id, 'period' => now()->addMonth()->format('Y-m'), 'year' => now()->addMonth()->year,
            'due_minor' => 5000, 'due_date' => now()->addMonth()->toDateString(), 'status' => 'to_pay']);
        $dep = Dependent::create(['member_id' => $m->id, 'first_name' => 'Junior', 'last_name' => 'Ngono', 'birth_date' => '2016-06-01', 'sex' => 'M', 'relationship' => 'child', 'status' => 'active']);
        $claim = CareClaim::create(['member_id' => $m->id, 'opened_on' => now()->toDateString(), 'status' => 'open']);
        $p = Prestation::create(['care_claim_id' => $claim->id, 'act_type_id' => $cons->id, 'provider_id' => 1, 'guarantee_version_id' => $v->id,
            'care_date' => now()->toDateString(), 'total_minor' => 10000, 'mutual_part_minor' => 8000, 'beneficiary_part_minor' => 2000, 'status' => 'captured', 'captured_by' => 99]);
        $oc = CareClaim::create(['member_id' => $other->id, 'opened_on' => now()->toDateString(), 'status' => 'open']);
        $op = Prestation::create(['care_claim_id' => $oc->id, 'act_type_id' => $cons->id, 'provider_id' => 1, 'guarantee_version_id' => $v->id,
            'care_date' => now()->toDateString(), 'total_minor' => 5000, 'mutual_part_minor' => 4000, 'beneficiary_part_minor' => 1000, 'status' => 'validated']);

        return compact('m', 'other', 'sub', 'paid', 'dep', 'p', 'op', 'claim');
    });
    tenancy()->end();
    $this->c = rtrim((string) config('app.url'), '/');
    $this->slug = $this->mutual->slug;
    $this->acc = account('Awa');
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

/** Code d'activation généré au back-office (affiché une fois). */
function pa4Code(int $memberId): string
{
    $res = as_('agent@equipe.test')->post(test()->host . "/adherents/{$memberId}/portail/code");
    $res->assertSessionHas('portal_code');

    return session('portal_code')['code'];
}

function pa4As($account)
{
    app('auth')->forgetGuards();

    return test()->actingAs($account, 'member');
}

it('scénario 1 : activation par code et date de naissance, puis consultation de l’adhésion et des garanties', function () {
    $code = pa4Code($this->ids['m']->id);
    expect(MemberActivationCode::where('member_id', $this->ids['m']->id)->value('code_hash'))->not->toBe($code);   // seule l'empreinte est stockée

    pa4As($this->acc)->get("{$this->c}/compte/mon-adhesion")->assertOk()->assertSee('Relier mon adhésion');
    pa4As($this->acc)->post("{$this->c}/compte/mon-adhesion/activer", ['code' => $code, 'birth_date' => '1991-04-02'])->assertSessionHasErrors('code');
    pa4As($this->acc)->post("{$this->c}/compte/mon-adhesion/activer", ['code' => strtolower($code), 'birth_date' => '1990-04-02'])
        ->assertRedirect("{$this->c}/compte/mon-adhesion/{$this->slug}");
    expect(MemberLink::where('public_account_id', $this->acc->id)->value('member_code'))->toBe('MUT-000800');
    expect(SocialNotification::where('public_account_id', $this->acc->id)->where('title', 'like', '%adhésion%')->exists())->toBeTrue();
    // Un code ne sert qu'une fois.
    $b = account('Bruno');
    pa4As($b)->post("{$this->c}/compte/mon-adhesion/activer", ['code' => $code, 'birth_date' => '1990-04-02'])->assertSessionHasErrors('code');

    // Tableau de bord : retard de cotisation → droits suspendus ; plafond familial et consommation.
    $base = "{$this->c}/compte/mon-adhesion/{$this->slug}";
    pa4As($this->acc)->get($base)->assertOk()->assertSee('ADHÉSION SUSPENDUE')->assertSee('En retard : 1 échéance(s)', false)
        ->assertSee('Famille')->assertSee('Plafond annuel familial')->assertSee('2', false);
    $this->mutual->run(fn () => app(\App\Services\Tenant\ContributionCollectionService::class)
        ->collect(Member::find($this->ids['m']->id), 5000, now()->toDateString(), 'cash', null, 1));
    pa4As($this->acc)->get($base)->assertOk()->assertSee('DROITS ACTIFS')->assertSee('À jour');

    // Garanties : taux, part de l'adhérent, délai d'attente et accord préalable, en langage simple.
    pa4As($this->acc)->get("{$base}/garanties")->assertOk()->assertSee('Consultations')->assertSee('80 %')->assertSee('20 %')
        ->assertSee('Accord préalable de la mutuelle', false)->assertSee('400 j');
    // Prestations, cotisations et reçu, carte (QR vers le sous-domaine), attestation.
    pa4As($this->acc)->get("{$base}/prestations")->assertOk()->assertSee('Consultation')->assertSee('PC-' . $this->ids['p']->id);
    pa4As($this->acc)->get("{$base}/prestations/{$this->ids['p']->id}")->assertOk()->assertSee('Part de la mutuelle');
    $pay = $this->mutual->run(fn () => \App\Models\Tenant\ContributionPayment::latest('id')->value('id'));
    pa4As($this->acc)->get("{$base}/cotisations")->assertOk()->assertSee('Payée')->assertSee('paiement en ligne', false);
    pa4As($this->acc)->get("{$base}/recus/{$pay}")->assertOk()->assertSee('Reçu de paiement')->assertSee('MUT-000800');
    pa4As($this->acc)->get("{$base}/carte")->assertOk()->assertSee('MUT-000800')->assertSee('<svg', false);
    pa4As($this->acc)->get("{$base}/attestation")->assertOk()->assertSee('Attestation d’adhésion', false)->assertSee('Junior');
    pa4As($this->acc)->get("{$base}/beneficiaires")->assertOk()->assertSee('NGONO Junior', false)->assertSee('Couvert(e)', false);
    pa4As($this->acc)->get("{$base}/prestataires")->assertOk();
    pa4As($this->acc)->get("{$base}/profil")->assertOk()->assertSee('699112233');
});

it('scénario 9 : jamais les données d’une autre mutuelle ni d’un autre adhérent', function () {
    $this->mutual->run(fn () => null);
    tenancy()->end();
    MemberLink::create(['public_account_id' => $this->acc->id, 'mutual_id' => $this->mutual->id, 'member_id' => $this->ids['m']->id, 'member_code' => 'MUT-000800']);
    $base = "{$this->c}/compte/mon-adhesion/{$this->slug}";

    // Prestation d'un AUTRE adhérent de la même mutuelle : introuvable.
    pa4As($this->acc)->get("{$base}/prestations/{$this->ids['op']->id}")->assertNotFound();
    pa4As($this->acc)->get("{$base}/prestations")->assertDontSee('PC-' . $this->ids['op']->id);
    // Mutuelle à laquelle le compte n'est pas relié (ou inexistante) : 404.
    pa4As($this->acc)->get("{$this->c}/compte/mon-adhesion/autre-mutuelle")->assertNotFound();
    $b = account('Bruno');
    pa4As($b)->get($base)->assertNotFound();
    pa4As($b)->post("{$base}/reclamations", ['category' => 'information', 'subject' => 'Question test', 'description' => 'Une question sans lien.'])->assertNotFound();
    // Sans connexion : vers la connexion.
    app('auth')->forgetGuards();
    $this->get($base)->assertRedirect();

    // Un adhérent déjà relié à un compte ne peut pas être relié à un autre.
    $code = pa4Code($this->ids['m']->id);
    pa4As($b)->post("{$this->c}/compte/mon-adhesion/activer", ['code' => $code, 'birth_date' => '1990-04-02'])
        ->assertSessionHasErrors(['code' => 'Cette adhésion est déjà reliée à un autre compte MX-CONNECT. Contactez votre mutuelle.']);
    // Retrait de l'accès par la mutuelle.
    as_('agent@equipe.test')->post("{$this->host}/adherents/{$this->ids['m']->id}/portail/retirer")->assertSessionHas('success');
    pa4As($this->acc)->get($base)->assertNotFound();
});

it('bénéficiaires et coordonnées : demandes appliquées seulement après validation par la mutuelle', function () {
    MemberLink::create(['public_account_id' => $this->acc->id, 'mutual_id' => $this->mutual->id, 'member_id' => $this->ids['m']->id, 'member_code' => 'MUT-000800']);
    $base = "{$this->c}/compte/mon-adhesion/{$this->slug}";
    pa4As($this->acc)->post("{$base}/beneficiaires", ['kind' => 'dependent_add', 'last_name' => 'Ngono', 'first_name' => 'Marie',
        'birth_date' => '2020-02-02', 'sex' => 'F', 'relationship' => 'adopted_child'])->assertRedirect("{$base}/beneficiaires");
    pa4As($this->acc)->post("{$base}/beneficiaires", ['kind' => 'dependent_remove', 'dependent_id' => $this->ids['dep']->id])->assertSessionHasErrors('note');
    pa4As($this->acc)->post("{$base}/coordonnees", ['address' => 'Quartier Bastos, Yaoundé'])->assertSessionHas('success');
    // Rien n'est appliqué tant que la mutuelle n'a pas décidé : jamais de couverture automatique.
    expect($this->mutual->run(fn () => Dependent::where('member_id', $this->ids['m']->id)->count()))->toBe(1);

    $req = $this->mutual->run(fn () => MemberChangeRequest::where('kind', 'dependent_add')->first());
    as_('tresor@equipe.test')->get("{$this->host}/demandes-adherents")->assertForbidden();
    as_('agent@equipe.test')->get("{$this->host}/demandes-adherents")->assertOk()->assertSee('Marie')->assertSee('Enfant adopté', false);
    as_('agent@equipe.test')->post("{$this->host}/demandes-adherents/{$req->id}", ['decision' => 'approved'])->assertSessionHas('success');
    $contact = $this->mutual->run(fn () => MemberChangeRequest::where('kind', 'contact')->first());
    as_('agent@equipe.test')->post("{$this->host}/demandes-adherents/{$contact->id}", ['decision' => 'rejected'])->assertSessionHas('error');
    as_('agent@equipe.test')->post("{$this->host}/demandes-adherents/{$contact->id}", ['decision' => 'approved'])->assertSessionHas('success');
    $this->mutual->run(function () {
        $marie = Dependent::where('first_name', 'Marie')->firstOrFail();
        expect($marie->relationship)->toBe('child')->and($marie->joined_on->toDateString())->toBe(now()->toDateString());
        expect(Member::find($this->ids['m']->id)->address)->toBe('Quartier Bastos, Yaoundé');
    });
    expect(SocialNotification::where('public_account_id', $this->acc->id)->where('title', 'like', '%bénéficiaire : acceptée%')->exists())->toBeTrue();
});

it('réclamations : envoyées depuis le portail, réponses de la mutuelle, notifications sans donnée médicale', function () {
    MemberLink::create(['public_account_id' => $this->acc->id, 'mutual_id' => $this->mutual->id, 'member_id' => $this->ids['m']->id, 'member_code' => 'MUT-000800']);
    $base = "{$this->c}/compte/mon-adhesion/{$this->slug}";
    pa4As($this->acc)->post("{$base}/reclamations", ['category' => 'provider_issue', 'subject' => 'Accueil au centre de santé', 'description' => 'On m’a demandé de payer la totalité malgré ma carte.'])
        ->assertRedirect("{$base}/reclamations");
    $c = $this->mutual->run(fn () => Complaint::firstOrFail());
    expect($c->channel)->toBe('online')->and($c->member_id)->toBe($this->ids['m']->id);

    as_('controle@equipe.test')->post("{$this->host}/reclamations/{$c->id}/message", ['body' => 'Nous contactons le centre de santé.'])->assertSessionHas('success');
    pa4As($this->acc)->get("{$base}/reclamations/{$c->id}")->assertOk()->assertSee('Nous contactons le centre de santé.')->assertSee('En cours de traitement');
    pa4As($this->acc)->post("{$base}/reclamations/{$c->id}/message", ['body' => 'Merci beaucoup.'])->assertSessionHas('success');
    as_('controle@equipe.test')->get("{$this->host}/reclamations/{$c->id}")->assertOk()->assertSee('Merci beaucoup.');

    // Validation d'une prestation : notification au compte, sans l'acte.
    as_('controle@equipe.test')->post("{$this->host}/recours/{$this->ids['claim']->id}/prestations/{$this->ids['p']->id}/validation")->assertSessionHas('success');
    $n = SocialNotification::where('public_account_id', $this->acc->id)->where('title', 'Une prise en charge a été validée')->firstOrFail();
    expect($n->body)->not->toContain('Consultation')->and($n->url)->toBe("/compte/mon-adhesion/{$this->slug}/prestations/{$this->ids['p']->id}");
    expect(SocialNotification::where('public_account_id', $this->acc->id)->where('title', 'like', 'Réponse de votre mutuelle%')->exists())->toBeTrue();
});
