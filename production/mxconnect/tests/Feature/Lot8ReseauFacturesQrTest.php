<?php

/*
 * Lot 8 — réseau de soins par garantie, facture PDF du prestataire, lecteur de QR du portail.
 * Testé dans une VRAIE base de mutuelle (mx_tests_tenant, provisionnée comme sur LWS).
 */

use App\Models\Tenant\ActType;
use App\Models\Tenant\Ceiling;
use App\Models\Tenant\Guarantee;
use App\Models\Tenant\GuaranteeVersion;
use App\Models\Tenant\Member;
use App\Models\Tenant\Prestation;
use App\Models\Tenant\ProviderConvention;
use App\Models\Tenant\ProviderInvoice;
use App\Models\Tenant\Subscription;
use App\Services\GuaranteeVersioningService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    provisionTestMutual();
    [$this->guarantee, $this->member, $this->act] = $this->mutual->run(function () {
        $g = Guarantee::create(['code' => 'ESS', 'name' => 'Essentielle', 'status' => 'active']);
        $v = GuaranteeVersion::create(['guarantee_id' => $g->id, 'version_no' => 1, 'valid_from' => '2026-01-01', 'base_contribution_minor' => 1000,
            'periodicity' => 'monthly', 'coverage_rate' => 80, 'copay_rate' => 20, 'observation_days' => 0]);
        $m = Member::create(['member_code' => 'MUT-000777', 'first_name' => 'Awa', 'last_name' => 'Nkolo', 'birth_date' => '1990-04-02',
            'sex' => 'F', 'status' => 'active', 'joined_at' => '2026-01-01']);
        Subscription::create(['member_id' => $m->id, 'guarantee_version_id' => $v->id, 'contribution_minor' => 1000, 'effective_date' => '2026-01-01',
            'observation_ends_on' => '2026-01-01', 'status' => 'validated', 'captured_by' => 1]);

        return [$g, $m, ActType::create(['code' => 'CONS', 'label' => 'Consultation', 'category' => 'consultation'])];
    });
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
    $this->mutual->run(fn () => Storage::disk('local')->deleteDirectory('provider-invoices'));
});

/** Établissement conventionné + son compte prestataire. */
function clinic(string $email, string $name): ProviderConvention
{
    asAdmin()->post(test()->host . '/parametres/prestataires', ['mode' => 'new', 'name' => $name, 'type' => 'health_center'])->assertRedirect();
    $conv = test()->mutual->run(fn () => ProviderConvention::latest('id')->firstOrFail());
    staffUser($email, ['provider'], ['care_provider_id' => $conv->care_provider_id]);

    return $conv;
}

/** Prise en charge déclarée par le portail puis validée par le contrôleur. */
function validatedDeclaration(string $email): Prestation
{
    as_($email)->post(test()->host . '/prestataire/verifier', ['code' => 'MUT-000777'])->assertSee('Droits ouverts');
    $check = test()->mutual->run(fn () => \App\Models\Tenant\RightsCheck::latest('id')->firstOrFail());
    as_($email)->post(test()->host . "/prestataire/declarer/{$check->id}", ['beneficiary' => 'member', 'act_type_id' => test()->act->id,
        'care_date' => now()->toDateString(), 'total' => '10000'])->assertRedirect();
    $p = test()->mutual->run(fn () => Prestation::latest('id')->firstOrFail());
    staffUser('ctrl' . $p->id . '@equipe.test', ['controller_validator']);
    as_('ctrl' . $p->id . '@equipe.test')->post(test()->host . "/recours/{$p->care_claim_id}/prestations/{$p->id}/validation")->assertSessionHas('success');

    return $p;
}

// --- Réseau de soins ---

it('restreint le réseau d’une garantie : un établissement hors réseau voit « Prestataire non conventionné »', function () {
    $a = clinic('accueil@csi-a.test', 'CSI A');
    $b = clinic('accueil@csi-b.test', 'Clinique B');
    $url = $this->host . "/garanties/{$this->guarantee->id}";

    asAdmin()->post($url . '/reseau', ['mode' => 'list', 'providers' => [$a->care_provider_id]])->assertRedirect($url)->assertSessionHas('success', 'Réseau de soins mis à jour.');
    expect($this->mutual->run(fn () => GuaranteeVersion::count()))->toBe(1)                 // version non utilisée : modifiée sur place
        ->and($this->mutual->run(fn () => GuaranteeVersion::first()->provider_network))->toBe([$a->care_provider_id]);
    asAdmin()->get($url)->assertOk()->assertSee('Réseau de soins')->assertSee('CSI A');

    as_('accueil@csi-a.test')->post($this->host . '/prestataire/verifier', ['code' => 'MUT-000777'])->assertSee('Droits ouverts');
    as_('accueil@csi-b.test')->post($this->host . '/prestataire/verifier', ['code' => 'MUT-000777'])->assertSee('Prestataire non conventionné');
    asAdmin()->get($this->host . "/parametres/prestataires/{$b->id}")->assertSee('hors du réseau de toutes les garanties');

    // Liste vide refusée ; prestataire non conventionné refusé ; non-administrateur refusé.
    asAdmin()->post($url . '/reseau', ['mode' => 'list', 'providers' => []])->assertSessionHasErrors('providers');
    asAdmin()->post($url . '/reseau', ['mode' => 'list', 'providers' => [999999]])->assertSessionHasErrors('providers.0');
    staffUser('gest@equipe.test', ['benefits_manager']);
    as_('gest@equipe.test')->post($url . '/reseau', ['mode' => 'all'])->assertForbidden();

    asAdmin()->post($url . '/reseau', ['mode' => 'all']);
    expect($this->mutual->run(fn () => GuaranteeVersion::first()->provider_network))->toBeNull();
});

it('ouvre une nouvelle version quand la version en cours a déjà servi, sans toucher au passé', function () {
    $a = clinic('accueil@csi-a.test', 'CSI A');
    validatedDeclaration('accueil@csi-a.test');                              // la V1 est maintenant verrouillée
    $this->mutual->run(fn () => Ceiling::create(['guarantee_version_id' => GuaranteeVersion::first()->id, 'ceiling_minor' => 500000, 'period' => 'annual']));
    $url = $this->host . "/garanties/{$this->guarantee->id}";

    asAdmin()->post($url . '/reseau', ['mode' => 'list', 'providers' => [$a->care_provider_id]])->assertSessionHasErrors('effective_from');
    $from = now()->addDays(3)->toDateString();
    asAdmin()->post($url . '/reseau', ['mode' => 'list', 'providers' => [$a->care_provider_id], 'effective_from' => $from])->assertRedirect($url);

    [$v1, $v2] = $this->mutual->run(fn () => GuaranteeVersion::orderBy('version_no')->get()->all());
    expect($v1->provider_network)->toBeNull()->and($v1->valid_to->toDateString())->toBe(now()->addDays(2)->toDateString())
        ->and($v2->version_no)->toBe(2)->and($v2->provider_network)->toBe([$a->care_provider_id])
        ->and((float) $v2->coverage_rate)->toBe(80.0)->and($v2->valid_from->toDateString())->toBe($from)
        ->and($this->mutual->run(fn () => Ceiling::where('guarantee_version_id', $v2->id)->value('ceiling_minor')))->toBe(500000);

    // Une nouvelle version « classique » reprend le réseau, les exclusions et les plafonds.
    $v3 = $this->mutual->run(fn () => app(GuaranteeVersioningService::class)->newVersion($this->guarantee, ['base_contribution_minor' => 1200,
        'periodicity' => 'monthly', 'coverage_rate' => 70, 'copay_rate' => 30, 'membership_fee_minor' => 0, 'observation_days' => 0], now()->addDays(10)->toDateString()));
    expect($v3->provider_network)->toBe([$a->care_provider_id])
        ->and($this->mutual->run(fn () => Ceiling::where('guarantee_version_id', $v3->id)->count()))->toBe(1);
});

// --- Facture PDF du prestataire ---

it('joint une facture PDF à la demande de paiement, privée et réservée', function () {
    clinic('accueil@csi-a.test', 'CSI A');
    clinic('accueil@csi-b.test', 'Clinique B');
    $p = validatedDeclaration('accueil@csi-a.test');
    $h = $this->host;

    // Un faux PDF est refusé et rien n'est enregistré.
    $fake = UploadedFile::fake()->createWithContent('facture.pdf', 'MZ-pas-un-pdf');
    as_('accueil@csi-a.test')->post($h . "/prestataire/factures/{$p->care_claim_id}", ['document' => $fake])->assertSessionHasErrors('document');
    expect($this->mutual->run(fn () => ProviderInvoice::count()))->toBe(0);

    as_('accueil@csi-a.test')->post($h . "/prestataire/factures/{$p->care_claim_id}", ['provider_reference' => 'F-12', 'document' => pdf('facture.pdf')])->assertSessionHas('success');
    $inv = $this->mutual->run(fn () => ProviderInvoice::firstOrFail());
    expect($inv->supporting_doc_path)->toStartWith('provider-invoices/')
        ->and($this->mutual->run(fn () => Storage::disk('local')->exists($inv->supporting_doc_path)))->toBeTrue();

    // Le prestataire retélécharge SA pièce ; un autre établissement non.
    $res = as_('accueil@csi-a.test')->get($h . "/prestataire/factures/{$inv->id}/piece")->assertOk();
    expect($res->headers->get('Content-Disposition'))->toContain('attachment')->toContain('facture-' . $inv->id . '-f-12.pdf')
        ->and($res->headers->get('Content-Security-Policy'))->toBe('sandbox')
        ->and($res->headers->get('Cache-Control'))->toContain('no-store')->toContain('private');
    as_('accueil@csi-b.test')->get($h . "/prestataire/factures/{$inv->id}/piece")->assertNotFound();
    $this->get('/media/' . $inv->supporting_doc_path)->assertNotFound();

    // Côté mutuelle : gestionnaire et contrôleur oui ; trésorier : montant et référence seulement.
    staffUser('gest@equipe.test', ['benefits_manager']);
    staffUser('tres@equipe.test', ['treasurer_accountant']);
    $doc = $h . "/recours/{$p->care_claim_id}/facture/{$inv->id}/piece";
    as_('gest@equipe.test')->get($doc)->assertOk();
    as_('gest@equipe.test')->get($h . "/recours/{$p->care_claim_id}")->assertSee('Facture PDF du prestataire')->assertSee('F-12');
    as_('tres@equipe.test')->get($doc)->assertForbidden();
    as_('tres@equipe.test')->get($h . "/recours/{$p->care_claim_id}")->assertSee('Facture PDF reçue')->assertSee('F-12');
});

it('permet de joindre la facture après coup, plus après le paiement', function () {
    clinic('accueil@csi-a.test', 'CSI A');
    $p = validatedDeclaration('accueil@csi-a.test');
    $h = $this->host;
    as_('accueil@csi-a.test')->post($h . "/prestataire/factures/{$p->care_claim_id}")->assertSessionHas('success');
    $inv = $this->mutual->run(fn () => ProviderInvoice::firstOrFail());
    expect($inv->supporting_doc_path)->toBeNull();
    as_('accueil@csi-a.test')->get($h . '/prestataire/factures')->assertSee('Joindre');

    as_('accueil@csi-a.test')->post($h . "/prestataire/factures/{$inv->id}/piece", ['document' => pdf('f.pdf')])->assertSessionHas('success');
    $first = $this->mutual->run(fn () => $inv->fresh()->supporting_doc_path);
    as_('accueil@csi-a.test')->post($h . "/prestataire/factures/{$inv->id}/piece", ['document' => pdf('f2.pdf')])->assertSessionHas('success');
    $this->mutual->run(fn () => expect(Storage::disk('local')->exists($first))->toBeFalse());        // l'ancienne pièce est effacée

    staffUser('tres@equipe.test', ['treasurer_accountant']);
    as_('tres@equipe.test')->post($h . "/recours/{$p->care_claim_id}/facture/{$inv->id}/paiement")->assertSessionHas('success');
    as_('accueil@csi-a.test')->post($h . "/prestataire/factures/{$inv->id}/piece", ['document' => pdf('f3.pdf')])->assertStatus(422);
});

// --- Lecteur de QR ---

it('autorise la caméra uniquement sur le portail et sert le lecteur en local', function () {
    clinic('accueil@csi-a.test', 'CSI A');
    $res = as_('accueil@csi-a.test')->get($this->host . '/prestataire')->assertOk()
        ->assertSee('Scanner avec la caméra')->assertSee('Photo de la carte')
        ->assertSee('/js/vendor/jsqr-1.4.0.min.js', false)->assertSee('/js/mx-scan.js', false);
    expect($res->headers->get('Permissions-Policy'))->toStartWith('camera=(self)');
    expect(asAdmin()->get($this->host . '/equipe')->headers->get('Permissions-Policy'))->toStartWith('camera=()');
    expect(file_exists(base_path('../js/vendor/jsqr-1.4.0.min.js')) && file_exists(base_path('../js/mx-scan.js')))->toBeTrue();
});
