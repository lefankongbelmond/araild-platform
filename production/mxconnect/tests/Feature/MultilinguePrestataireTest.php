<?php

/*
 * Multilingue M4 (2026-12-03) : espace prestataire central traduit (écrans, menus, rôles, droits,
 * messages du serveur) et avis des mutuelles affichés dans la langue de CHAQUE personne de l'établissement.
 */

use App\Models\Central\Locale;
use App\Models\Provider\ProviderNotification;
use App\Models\Tenant\ActType;
use App\Models\Tenant\CareClaim;
use App\Models\Tenant\Guarantee;
use App\Models\Tenant\GuaranteeVersion;
use App\Models\Tenant\Member;
use App\Models\Tenant\Prestation;
use App\Models\Tenant\Subscription;
use App\Support\Locales;

beforeEach(function () {
    provisionTestMutual();
    $this->c = rtrim((string) config('app.url'), '/');
    staffUser('saisie@equipe.test', ['benefits_manager']);
    $this->conv = conventioned('CSI Akono');
    $this->ids = $this->mutual->run(function () {
        $g = Guarantee::create(['code' => 'ESS', 'name' => 'Essentielle', 'status' => 'active']);
        $v = GuaranteeVersion::create(['guarantee_id' => $g->id, 'version_no' => 1, 'valid_from' => '2026-01-01', 'base_contribution_minor' => 1000,
            'periodicity' => 'monthly', 'coverage_rate' => 80, 'copay_rate' => 20, 'observation_days' => 0]);
        $m = Member::create(['member_code' => 'MUT-M4-01', 'first_name' => 'Awa', 'last_name' => 'Ngono', 'birth_date' => '1990-04-02', 'sex' => 'F', 'status' => 'active', 'joined_at' => '2026-01-01']);
        Subscription::create(['member_id' => $m->id, 'guarantee_version_id' => $v->id, 'contribution_minor' => 1000, 'effective_date' => '2026-01-01',
            'observation_ends_on' => '2026-01-01', 'status' => 'validated', 'captured_by' => 1]);
        $act = ActType::create(['code' => 'CONS', 'label' => 'Consultation', 'category' => 'consultation']);
        $by = \App\Models\Tenant\User::where('email', 'saisie@equipe.test')->value('id');
        $claim = CareClaim::create(['member_id' => $m->id, 'opened_on' => now()->toDateString(), 'status' => 'open']);
        $p = Prestation::create(['care_claim_id' => $claim->id, 'act_type_id' => $act->id, 'provider_id' => $this->conv->care_provider_id,
            'guarantee_version_id' => $v->id, 'care_date' => now()->toDateString(), 'total_minor' => 10000, 'mutual_part_minor' => 8000,
            'beneficiary_part_minor' => 2000, 'status' => 'captured', 'captured_by' => $by]);

        return ['claim' => $claim->id, 'p' => $p->id];
    });
    tenancy()->end();
    Locale::query()->update(['active' => true]);
    Locales::forgetCache();
    $this->admin = proAccount('direction@akono.test', $this->conv->care_provider_id, 'admin');
    $this->doctor = proAccount('docteur@akono.test', $this->conv->care_provider_id, 'doctor');
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
    Locales::forgetCache();
    app()->setLocale('fr');
});

it('affiche l’espace prestataire dans la langue choisie par la personne', function () {
    $this->admin->forceFill(['locale' => 'pt'])->save();

    asPro($this->admin)->get($this->c . '/espace-prestataire')->assertOk()->assertSee('lang="pt"', false)
        ->assertSee('Avisos das mútuas')->assertSee('Gestão do estabelecimento')->assertSee('Administrador do estabelecimento')
        ->assertDontSee('Gestion de l’établissement');
    // Rôles et droits de l'établissement (listes PHP traduites à l'affichage).
    asPro($this->admin)->get($this->c . '/espace-prestataire/personnel')->assertOk()
        ->assertSee('Pessoal e utilizadores')->assertSee('Percurso de cuidados')->assertSee('Verificar os direitos de um aderente')->assertSee('Parteira');
    // Message du serveur (validation d'un contrôleur de l'espace).
    asPro($this->admin)->from($this->c . '/espace-prestataire/parametres')->post($this->c . '/espace-prestataire/parametres/mot-de-passe', [
        'current_password' => 'x', 'password' => 'Abcdefgh12', 'password_confirmation' => 'Autre1234567',
    ])->assertSessionHasErrors(['password' => 'As duas palavras-passe não coincidem.']);

    // Arabe : de droite à gauche.
    $this->doctor->forceFill(['locale' => 'ar'])->save();
    asPro($this->doctor)->get($this->c . '/espace-prestataire')->assertOk()->assertSee('dir="rtl"', false)->assertSee('إشعارات التعاضديات');
});

it('affiche chaque avis de la mutuelle dans la langue de la personne qui le lit', function () {
    $i = $this->ids;
    asAdmin()->post($this->host . "/recours/{$i['claim']}/prestations/{$i['p']}/validation")->assertRedirect();
    tenancy()->end();

    $n = ProviderNotification::where('care_provider_id', $this->conv->care_provider_id)->firstOrFail();
    // Le texte enregistré reste en français (anciens écrans, exports) ; la forme traduisible est gardée à côté.
    expect($n->title)->toContain('Prise en charge n° ' . $i['p'] . ' validée')->and($n->body)->toContain('MUT-M4-01')
        ->and($n->getAttributes()['i18n'])->not->toBeNull();

    $this->admin->forceFill(['locale' => 'en'])->save();
    asPro($this->admin)->get($this->c . '/espace-prestataire/avis')->assertOk()
        ->assertSee('Claim no. ' . $i['p'] . ' approved')->assertSee('Member MUT-M4-01')->assertSee('Mutuelle Équipe')
        ->assertDontSee('Prise en charge n° ' . $i['p'] . ' validée');
    $this->doctor->forceFill(['locale' => 'es'])->save();
    asPro($this->doctor)->get($this->c . '/espace-prestataire/avis')->assertOk()->assertSee('Cobertura n.º ' . $i['p'] . ' validada');

    // Un avis antérieur (sans forme traduisible) s'affiche tel qu'il a été enregistré.
    ProviderNotification::create(['care_provider_id' => $this->conv->care_provider_id, 'kind' => 'claim_validated', 'title' => 'Ancien avis enregistré',
        'body' => 'Texte d’origine', 'target' => 'claims', 'permission' => 'claims.view']);
    asPro($this->admin)->get($this->c . '/espace-prestataire/avis')->assertSee('Ancien avis enregistré')->assertSee('Texte d’origine');
});

it('ne laisse aucun titre ou libellé lié (:title) en français non traduit dans les vues', function () {
    $bad = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views')));
    foreach ($it as $f) {
        if (! str_ends_with($f->getFilename(), '.blade.php')) {
            continue;
        }
        foreach (file($f->getPathname()) as $no => $line) {
            if (preg_match('/:(title|label|hint|kicker|subtitle|placeholder)="\'[A-ZÀ-Üa-zé][^\']*[a-zé ]/u', $line) && ! str_contains($line, '__(')) {
                $bad[] = str_replace(resource_path('views') . '/', '', $f->getPathname()) . ':' . ($no + 1);
            }
        }
    }
    expect($bad)->toBe([]);
});
