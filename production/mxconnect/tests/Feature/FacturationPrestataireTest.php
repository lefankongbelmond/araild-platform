<?php

/*
 * Portail prestataire V2, phase 5 (2026-11-26) : facturation et paiements.
 * Bordereau à lignes, contrôle ligne par ligne (« 4 yeux »), rejet partiel et renvoi,
 * validée ≠ payée, paiement enregistré seulement avec sa référence, exports CSV.
 * VRAIE base de mutuelle (ou tables préfixées).
 */

use App\Models\Provider\ProviderNotification;
use App\Models\Tenant\ActType;
use App\Models\Tenant\CareClaim;
use App\Models\Tenant\Guarantee;
use App\Models\Tenant\GuaranteeVersion;
use App\Models\Tenant\Member;
use App\Models\Tenant\Prestation;
use App\Models\Tenant\ProviderInvoice;
use App\Models\Tenant\ProviderInvoiceLine;
use App\Models\Tenant\Subscription;
use App\Models\Tenant\TreasuryMovement;
use App\Models\Tenant\User;

beforeEach(function () {
    provisionTestMutual();
    staffUser('ctrl@equipe.test', ['controller_validator']);
    staffUser('tres@equipe.test', ['treasurer_accountant']);
    staffUser('audit@equipe.test', ['auditor']);
    staffUser('gest@equipe.test', ['benefits_manager']);
    $this->conv = conventioned('CSI Akono');
    $this->prov = staffUser('accueil@akono.test', ['provider'], ['care_provider_id' => $this->conv->care_provider_id]);
    $this->p = $this->mutual->run(function () {
        $g = Guarantee::create(['code' => 'ESS', 'name' => 'Essentielle', 'status' => 'active']);
        $v = GuaranteeVersion::create(['guarantee_id' => $g->id, 'version_no' => 1, 'valid_from' => '2026-01-01', 'base_contribution_minor' => 1000,
            'periodicity' => 'monthly', 'coverage_rate' => 80, 'copay_rate' => 20, 'observation_days' => 0]);
        $m = Member::create(['member_code' => 'MUT-FACT-01', 'first_name' => 'Awa', 'last_name' => 'Ngono', 'birth_date' => '1990-04-02', 'sex' => 'F', 'status' => 'active', 'joined_at' => '2026-01-01']);
        Subscription::create(['member_id' => $m->id, 'guarantee_version_id' => $v->id, 'contribution_minor' => 1000, 'effective_date' => '2026-01-01',
            'observation_ends_on' => '2026-01-01', 'status' => 'validated', 'captured_by' => 1]);
        $act = ActType::create(['code' => 'CONS', 'label' => 'Consultation de médecine générale', 'category' => 'consultation']);
        $by = User::where('email', 'gest@equipe.test')->value('id');
        $out = [];
        foreach ([[10000, 8000], [5000, 4000], [2500, 2000]] as $i => [$total, $mutual]) {
            $claim = CareClaim::create(['member_id' => $m->id, 'opened_on' => now()->subDays(3 - $i)->toDateString(), 'status' => 'open']);
            $out[] = Prestation::create(['care_claim_id' => $claim->id, 'act_type_id' => $act->id, 'provider_id' => $this->conv->care_provider_id,
                'guarantee_version_id' => $v->id, 'care_date' => now()->subDays(3 - $i)->toDateString(), 'total_minor' => $total, 'mutual_part_minor' => $mutual,
                'beneficiary_part_minor' => $total - $mutual, 'status' => 'validated', 'captured_by' => $by])->id;
        }

        return $out;
    });
    tenancy()->end();
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('envoie un bordereau à lignes, contrôlé ligne par ligne, avec rejet partiel et renvoi', function () {
    $h = $this->host;
    [$a, $b, $c] = $this->p;

    // Les 3 prestations validées sont à facturer ; l'établissement en regroupe 3 dans un bordereau.
    as_('accueil@akono.test')->get($h . '/prestataire/factures')->assertOk()->assertSee('Prestations validées à facturer')->assertSee('Demander le paiement')->assertSee('MUT-FACT-01');
    as_('accueil@akono.test')->post($h . '/prestataire/factures/bordereau', [])->assertSessionHasErrors('prestations');
    as_('accueil@akono.test')->post($h . '/prestataire/factures/bordereau', ['prestations' => [$a, $b, $c], 'provider_reference' => 'BRD-11'])->assertRedirect();
    $inv = $this->mutual->run(fn () => ProviderInvoice::with('lines')->firstOrFail());
    expect($inv->reference)->toStartWith('BOR-')->and($inv->kind)->toBe('batch')->and($inv->lines)->toHaveCount(3)
        ->and($inv->amount_minor)->toBe(14000)->and($inv->billed_minor)->toBe(14000)->and($inv->status)->toBe('captured')->and($inv->care_claim_id)->toBeNull();

    // Jamais deux fois la même prestation (double clic, ancien bouton, autre bordereau).
    as_('accueil@akono.test')->post($h . '/prestataire/factures/bordereau', ['prestations' => [$a]])->assertSessionHas('error');
    $claimA = $this->mutual->run(fn () => Prestation::find($a)->care_claim_id);
    as_('accueil@akono.test')->post($h . "/prestataire/factures/{$claimA}")->assertStatus(422);
    expect($this->mutual->run(fn () => ProviderInvoice::count()))->toBe(1);

    // Droits : l'auditeur lit, ne contrôle pas ; le trésorier ne contrôle pas et ne paie pas une facture non validée.
    as_('audit@equipe.test')->get($h . "/factures-prestataires/{$inv->id}")->assertOk()->assertDontSee('Accepter')->assertDontSee('Consultation de médecine générale');
    as_('audit@equipe.test')->post($h . "/factures-prestataires/{$inv->id}/lignes", ['decision' => 'accept_all'])->assertForbidden();
    as_('tres@equipe.test')->post($h . "/factures-prestataires/{$inv->id}/lignes", ['decision' => 'accept_all'])->assertForbidden();
    as_('tres@equipe.test')->post($h . "/factures-prestataires/{$inv->id}/paiement", ['mode' => 'bank', 'payment_reference' => 'VIR-1', 'paid_on' => now()->toDateString()])
        ->assertSessionHas('error', fn ($m) => str_contains($m, 'Contrôlez d’abord'));
    as_('ctrl@equipe.test')->get($h . '/factures-prestataires')->assertOk()->assertSee('À contrôler')->assertSee('Contrôler les lignes');
    as_('ctrl@equipe.test')->get($h . "/factures-prestataires/{$inv->id}")->assertOk()->assertSee('Accepter les 3 lignes en attente')->assertSee('Consultation de médecine générale');

    // Contrôle : une ligne rejetée (motif obligatoire), les deux autres acceptées.
    $lines = $this->mutual->run(fn () => ProviderInvoiceLine::orderBy('id')->pluck('id', 'prestation_id'));
    as_('ctrl@equipe.test')->post($h . "/factures-prestataires/{$inv->id}/lignes", ['line' => $lines[$c], 'decision' => 'rejected', 'note' => ''])->assertSessionHas('error');
    as_('ctrl@equipe.test')->post($h . "/factures-prestataires/{$inv->id}/lignes", ['line' => $lines[$c], 'decision' => 'rejected', 'note' => 'Montant supérieur au tarif conventionnel'])->assertSessionHas('success', 'Décision enregistrée.');
    as_('ctrl@equipe.test')->post($h . "/factures-prestataires/{$inv->id}/lignes", ['decision' => 'accept_all'])->assertSessionHas('success', fn ($m) => str_contains($m, 'facture validée'));
    $inv = $this->mutual->run(fn () => $inv->fresh());
    expect($inv->status)->toBe('processed')->and($inv->amount_minor)->toBe(12000)->and($inv->billed_minor)->toBe(14000)->and($inv->checked_at)->not->toBeNull();
    tenancy()->end();
    $n = ProviderNotification::where('care_provider_id', $this->conv->care_provider_id)->latest('id')->first();
    expect($n->kind)->toBe('invoice_checked')->and($n->body)->toContain('2 ligne(s)')->toContain('1 ligne(s) rejetée(s)')->not->toContain('Consultation');

    // L'établissement voit le motif et la prestation rejetée revient « à facturer » (renvoi).
    as_('accueil@akono.test')->get($h . "/prestataire/factures/{$inv->id}")->assertOk()->assertSee('Montant supérieur au tarif conventionnel')->assertSee('Validée, en attente de paiement');
    as_('accueil@akono.test')->get($h . '/prestataire/factures')->assertSee('Renvoi : rejetée sur la facture ' . $inv->reference, false);
    as_('accueil@akono.test')->post($h . '/prestataire/factures/bordereau', ['prestations' => [$a]])->assertSessionHas('error');   // acceptée : pas de renvoi
    as_('accueil@akono.test')->post($h . '/prestataire/factures/bordereau', ['prestations' => [$c]])->assertRedirect();
    expect($this->mutual->run(fn () => ProviderInvoice::latest('id')->first()->amount_minor))->toBe(2000);
});

it('ne marque « payée » qu’un paiement effectué, avec sa référence, une seule fois', function () {
    $h = $this->host;
    as_('accueil@akono.test')->post($h . '/prestataire/factures/bordereau', ['prestations' => $this->p])->assertRedirect();
    $inv = $this->mutual->run(fn () => ProviderInvoice::firstOrFail());
    as_('ctrl@equipe.test')->post($h . "/factures-prestataires/{$inv->id}/lignes", ['decision' => 'accept_all'])->assertSessionHas('success');

    // Validée ≠ payée : rien en trésorerie tant que le trésorier n'a pas enregistré le paiement.
    expect($this->mutual->run(fn () => TreasuryMovement::count()))->toBe(0);
    as_('accueil@akono.test')->get($h . '/prestataire/factures')->assertSee('Validée, à payer')->assertDontSee('Payée le');
    as_('tres@equipe.test')->get($h . '/factures-prestataires?statut=a-payer')->assertOk()->assertSee('Enregistrer le paiement');
    as_('ctrl@equipe.test')->post($h . "/factures-prestataires/{$inv->id}/paiement", ['mode' => 'bank', 'payment_reference' => 'VIR-1', 'paid_on' => now()->toDateString()])->assertForbidden();

    // Référence obligatoire, date ni future ni absente.
    $ok = ['mode' => 'mobile_money', 'payment_reference' => 'MM-778899', 'paid_on' => now()->toDateString()];
    as_('tres@equipe.test')->post($h . "/factures-prestataires/{$inv->id}/paiement", ['payment_reference' => ''] + $ok)->assertSessionHasErrors('payment_reference');
    as_('tres@equipe.test')->post($h . "/factures-prestataires/{$inv->id}/paiement", ['paid_on' => now()->addDay()->toDateString()] + $ok)->assertSessionHas('error', fn ($m) => str_contains($m, 'futur'));
    as_('tres@equipe.test')->post($h . "/factures-prestataires/{$inv->id}/paiement", $ok)->assertSessionHas('success');
    as_('tres@equipe.test')->post($h . "/factures-prestataires/{$inv->id}/paiement", $ok)->assertSessionHas('error', fn ($m) => str_contains($m, 'déjà payée'));

    $state = $this->mutual->run(fn () => [$inv->fresh(), TreasuryMovement::all()]);
    expect($state[0]->status)->toBe('paid')->and($state[0]->payment_reference)->toBe('MM-778899')->and($state[0]->payment_mode)->toBe('mobile_money')
        ->and($state[0]->paid_on->toDateString())->toBe(now()->toDateString());
    expect($state[1])->toHaveCount(1)->and($state[1][0]->amount_minor)->toBe(14000)->and($state[1][0]->mode)->toBe('mobile_money')->and($state[1][0]->linked_reference)->toBe($state[0]->reference);
    tenancy()->end();
    expect(ProviderNotification::where('kind', 'invoice_paid')->first()->body)->toContain('MM-778899');
    as_('accueil@akono.test')->get($h . '/prestataire/factures')->assertSee('Payée le')->assertSee('Réf. MM-778899');
    as_('accueil@akono.test')->get($h . "/prestataire/factures/{$inv->id}")->assertSee('Paiement effectué par la mutuelle')->assertSee('référence MM-778899');
});

it('rejette toute une facture : ses prestations redeviennent facturables', function () {
    $h = $this->host;
    as_('accueil@akono.test')->post($h . '/prestataire/factures/bordereau', ['prestations' => $this->p])->assertRedirect();
    $inv = $this->mutual->run(fn () => ProviderInvoice::firstOrFail());
    as_('ctrl@equipe.test')->post($h . "/factures-prestataires/{$inv->id}/rejet", ['note' => 'Bordereau en double'])->assertSessionHas('success');
    expect($this->mutual->run(fn () => ProviderInvoiceLine::where('status', 'rejected')->count()))->toBe(3);
    as_('accueil@akono.test')->get($h . '/prestataire/factures')->assertSee('Bordereau en double');
    as_('accueil@akono.test')->post($h . '/prestataire/factures/bordereau', ['prestations' => $this->p])->assertRedirect();
    expect($this->mutual->run(fn () => ProviderInvoice::where('status', 'captured')->count()))->toBe(1);
});

it('applique le contrôle « 4 yeux » à une facture saisie par la mutuelle, et exporte les lignes', function () {
    $h = $this->host;
    $claim = $this->mutual->run(fn () => Prestation::find($this->p[0])->care_claim_id);
    as_('gest@equipe.test')->post($h . "/recours/{$claim}/facture", ['provider_id' => $this->conv->care_provider_id])->assertSessionHas('success');
    as_('gest@equipe.test')->post($h . "/recours/{$claim}/facture", ['provider_id' => $this->conv->care_provider_id])->assertSessionHas('error');
    $inv = $this->mutual->run(fn () => ProviderInvoice::with('lines')->firstOrFail());
    expect($inv->lines)->toHaveCount(1)->and($inv->kind)->toBe('claim')->and($inv->care_claim_id)->toBe($claim);
    // Le gestionnaire qui l'a saisie ne la contrôle pas (et n'en a d'ailleurs pas le droit).
    as_('gest@equipe.test')->post($h . "/factures-prestataires/{$inv->id}/lignes", ['decision' => 'accept_all'])->assertForbidden();
    asAdmin()->post($h . "/factures-prestataires/{$inv->id}/lignes", ['decision' => 'accept_all'])->assertSessionHas('success');

    // Exports : registre (factures et lignes, sans l'acte) et portail (ses seules lignes).
    $csv = as_('tres@equipe.test')->get($h . '/factures-prestataires/export.csv?statut=a-payer&lignes=1')->assertOk()->getContent();
    expect($csv)->toContain($inv->reference)->toContain('MUT-FACT-01')->toContain('Acceptée')->not->toContain('Consultation');
    $csv = as_('tres@equipe.test')->get($h . '/factures-prestataires/export.csv?statut=a-payer')->assertOk()->getContent();
    expect($csv)->toContain('Montant validé')->toContain($inv->reference);
    $csv = as_('accueil@akono.test')->get($h . '/prestataire/factures/export.csv')->assertOk()->getContent();
    expect($csv)->toContain($inv->reference)->toContain('Ngono Awa');

    // Un autre établissement ne voit ni la facture ni ses lignes.
    $other = conventioned('Clinique Autre');
    staffUser('accueil@autre.test', ['provider'], ['care_provider_id' => $other->care_provider_id]);
    as_('accueil@autre.test')->get($h . "/prestataire/factures/{$inv->id}")->assertNotFound();
    expect(as_('accueil@autre.test')->get($h . '/prestataire/factures/export.csv')->getContent())->not->toContain($inv->reference);
});
