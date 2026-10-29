<?php

/*
 * Application Android bêta (2026-11-09) : parcours d'entrée (maquettes 1 à 6), documents
 * légaux versionnés (jamais pré-cochés, acceptation tracée, nouvelle version à revalider),
 * liaison du domaine, retours des testeurs, réglages super-admin.
 */

use App\Models\Central\NetworkUser;
use App\Models\Central\PublicAccount;
use App\Models\Social\BetaFeedback;
use App\Models\Social\LegalAcceptance;
use App\Models\Social\LegalDocument;
use App\Models\Social\Post;
use App\Models\Social\SocialSetting;
use App\Services\Social\LegalService;
use Spatie\Permission\Models\Role;

if (! function_exists('networkUser')) {
    function networkUser(string $role = 'super_admin'): NetworkUser
    {
        Role::findOrCreate($role, 'network');
        $u = NetworkUser::factory()->create(['mfa_enabled' => true]);
        $u->assignRole($role);

        return $u;
    }
}

function asSuperAdmin(string $role = 'super_admin'): \Illuminate\Foundation\Testing\TestCase
{
    return test()->actingAs(networkUser($role), 'network')->withSession(['mfa_passed' => true]);
}

function legalDoc(string $type): LegalDocument
{
    return app(LegalService::class)->currentOf($type);
}

it('oriente le démarrage : onboarding une seule fois, puis connexion, maintenance et version minimale', function () {
    $this->get(route('social.app.start', ['source' => 'android', 'v' => 1]))->assertOk()->assertSee(route('social.app.onboarding'), false);
    $this->withCookie('mx_onb', '1')->get(route('social.app.start'))->assertOk()->assertSee(route('social.app.auth'), false);

    $this->get(route('social.app.onboarding', ['etape' => 2]))->assertOk()->assertSee('Passer');
    $this->get(route('social.app.onboarding.done'))->assertRedirect(route('social.app.auth'))->assertCookie('mx_onb');

    SocialSetting::put('android_min_version', 5);
    $this->get(route('social.app.start', ['v' => 3]))->assertStatus(426)->assertSee('Mise à jour');
    $this->get(route('social.app.start', ['v' => 5]))->assertOk();

    SocialSetting::put('android_maintenance', '1');
    SocialSetting::put('android_maintenance_message', 'Maintenance du soir');
    $this->get(route('social.app.start'))->assertStatus(503)->assertSee('Maintenance du soir');
    $this->getJson(route('social.app.status'))->assertOk()->assertJson(['ok' => false, 'min_version' => 5]);
});

it('envoie un membre connecté vers l’accueil, ou vers les conditions si un document est dû', function () {
    $ok = account('Awa');
    $this->actingAs($ok, 'member')->get(route('social.app.start'))->assertSee(route('social.home'), false);

    $due = account('Binta', acceptLegal: false);
    $this->actingAs($due, 'member')->get(route('social.app.start'))->assertSee('compte/conditions', false);
});

it('présente des cases jamais pré-cochées et crée un compte par e-mail seul depuis l’application', function () {
    $privacy = legalDoc('privacy');
    $html = $this->get(route('social.app.auth', ['onglet' => 'inscription']))->assertOk()->assertSee('doc_' . $privacy->id, false)->getContent();
    expect($html)->not->toMatch('/name="doc_\d+"[^>]*checked/');

    // Sans cocher : refus, aucun compte.
    $this->post(route('social.register'), ['app' => '1', 'first_name' => 'Awa', 'last_name' => 'Mbida', 'email' => 'awa@exemple.cm', 'password' => 'motdepasse1'])
        ->assertSessionHasErrors('doc_' . $privacy->id);
    expect(PublicAccount::where('email', 'awa@exemple.cm')->exists())->toBeFalse();

    // Ni téléphone ni e-mail : refus.
    $this->post(route('social.register'), ['app' => '1', 'first_name' => 'Awa', 'last_name' => 'Mbida', 'password' => 'motdepasse1', 'doc_' . $privacy->id => '1'])
        ->assertSessionHasErrors(['phone', 'email']);

    $this->post(route('social.register'), ['app' => '1', 'first_name' => 'Awa', 'last_name' => 'Mbida', 'email' => 'awa@exemple.cm', 'phone_cc' => '+237', 'phone_local' => '',
        'password' => 'motdepasse1', 'doc_' . $privacy->id => '1'])
        ->assertRedirect(); // vers « Conditions » : les règles communautaires restent à accepter
    $a = PublicAccount::where('email', 'awa@exemple.cm')->firstOrFail();
    expect($a->phone)->toBeNull();
    $row = LegalAcceptance::where('public_account_id', $a->id)->sole();
    expect([$row->type, $row->version, $row->action, $row->channel])->toBe(['privacy', $privacy->version, 'acknowledged', 'android']);
    expect(app(LegalService::class)->pendingFor($a)->pluck('type')->all())->toBe(['community']);
});

it('crée un compte avec téléphone (indicatif + numéro local) et garde le formulaire web inchangé', function () {
    $privacy = legalDoc('privacy');
    $this->post(route('social.register'), ['app' => '1', 'first_name' => 'Jean', 'last_name' => 'Ndi', 'phone_cc' => '+225', 'phone_local' => '07 00 00 00 01',
        'password' => 'motdepasse1', 'doc_' . $privacy->id => '1']);
    expect(PublicAccount::where('phone', '+2250700000001')->exists())->toBeTrue();
    auth('member')->logout();

    // Formulaire web : case unique « terms » (règles communautaires + confidentialité).
    $this->post(route('social.register'), ['first_name' => 'Paul', 'last_name' => 'Eto', 'phone' => '+237690000002', 'password' => 'motdepasse1', 'password_confirmation' => 'motdepasse1'])
        ->assertSessionHasErrors('terms');
    $this->post(route('social.register'), ['first_name' => 'Paul', 'last_name' => 'Eto', 'phone' => '+237690000002', 'password' => 'motdepasse1', 'password_confirmation' => 'motdepasse1', 'terms' => '1'])
        ->assertRedirect(route('social.mutuals'));
    $p = PublicAccount::where('phone', '+237690000002')->firstOrFail();
    expect(LegalAcceptance::where('public_account_id', $p->id)->pluck('type')->sort()->values()->all())->toBe(['community', 'privacy']);
    expect(app(LegalService::class)->pendingFor($p))->toBeEmpty();
});

it('laisse lire mais bloque les actions tant que les documents dus ne sont pas validés', function () {
    $a = account('Awa', acceptLegal: false);
    $this->actingAs($a, 'member')->get(route('social.home'))->assertOk()->assertSee('compte/conditions', false);
    $this->post(route('social.post.store'), ['body' => 'Bonjour'])->assertRedirect(route('social.legal.pending'));
    $this->postJson(route('social.post.store'), ['body' => 'Bonjour'])->assertStatus(409);
    expect(Post::count())->toBe(0);

    // L'écran ne pré-coche rien ; valider sans cocher est refusé.
    $html = $this->get(route('social.legal.pending'))->assertOk()->getContent();
    expect($html)->not->toMatch('/name="doc_\d+"[^>]*checked/');
    $docs = app(LegalService::class)->pendingFor($a);
    $this->post(route('social.legal.accept'), [])->assertSessionHasErrors('doc_' . $docs->first()->id);

    $this->post(route('social.legal.accept'), $docs->mapWithKeys(fn ($d) => ['doc_' . $d->id => '1'])->all() + ['suite' => '/app/pret'])->assertRedirect('/app/pret');
    expect(LegalAcceptance::where('public_account_id', $a->id)->count())->toBe($docs->count());
    $this->post(route('social.post.store'), ['body' => 'Bonjour'])->assertRedirect();
    expect(Post::count())->toBe(1);

    // Redirection « suite » limitée au site.
    $this->post(route('social.legal.accept'), ['suite' => '//exemple.com'])->assertRedirect(route('social.home'));
});

it('publie une nouvelle version : l’ancienne est archivée, la revalidation n’est imposée que si demandée', function () {
    $a = account('Awa');
    $old = legalDoc('community');
    $this->travel(5)->seconds();   // publication postérieure à la création du compte

    asSuperAdmin()->post(route('network.android.legal.save'), ['type' => 'community', 'version' => $old->version, 'title' => 'x', 'body' => str_repeat('Texte ', 10)])
        ->assertSessionHasErrors('version');
    asSuperAdmin()->post(route('network.android.legal.save'), ['type' => 'community', 'version' => '1.1', 'title' => 'Règles', 'body' => str_repeat('Texte des règles. ', 5)])
        ->assertRedirect(route('network.android.legal'));
    $draft = LegalDocument::where('type', 'community')->where('version', '1.1')->sole();
    expect($draft->status)->toBe('draft');
    expect(app(LegalService::class)->pendingFor($a))->toBeEmpty();           // brouillon invisible

    asSuperAdmin()->post(route('network.android.legal.publish', $draft->id))->assertRedirect();
    expect($old->fresh()->status)->toBe('archived');
    expect(app(LegalService::class)->pendingFor($a->fresh()))->toBeEmpty();   // pas de revalidation demandée : le compte existant n'est pas bloqué

    asSuperAdmin()->post(route('network.android.legal.save'), ['type' => 'community', 'version' => '2.0', 'title' => 'Règles', 'body' => str_repeat('Nouvelles règles. ', 5), 'requires_reacceptance' => '1']);
    $v2 = LegalDocument::where('type', 'community')->where('version', '2.0')->sole();
    asSuperAdmin()->post(route('network.android.legal.publish', $v2->id));
    expect(app(LegalService::class)->pendingFor($a->fresh())->pluck('version')->all())->toBe(['2.0']);

    // Une version publiée n'est plus modifiable ; l'ancienne reste consultable.
    asSuperAdmin()->post(route('network.android.legal.save'), ['id' => $v2->id, 'type' => 'community', 'version' => '2.0', 'title' => 'x', 'body' => str_repeat('y ', 20)])->assertNotFound();
    $this->get(route('social.legal.show', ['type' => 'community', 'version' => $old->version]))->assertOk();
});

it('refuse de publier un modèle « À COMPLÉTER » sans confirmation explicite', function () {
    $tpl = LegalDocument::where('type', 'terms')->where('status', 'draft')->firstOrFail();
    expect($tpl->is_template)->toBeTrue();
    expect(app(LegalService::class)->currentOf('terms'))->toBeNull();   // conditions : modèle non publié

    asSuperAdmin()->post(route('network.android.legal.publish', $tpl->id))->assertSessionHas('error');
    expect($tpl->fresh()->status)->toBe('draft');
    asSuperAdmin()->post(route('network.android.legal.publish', $tpl->id), ['confirm_template' => '1']);
    expect($tpl->fresh()->status)->toBe('published');
});

it('publie la liaison du domaine seulement avec une empreinte valide', function () {
    $this->getJson('/.well-known/assetlinks.json')->assertOk()->assertExactJson([]);

    asSuperAdmin()->post(route('network.android.settings.save'), ['package' => 'com.araild.mxconnect', 'sha256' => 'pas-une-empreinte', 'min' => 1, 'latest' => 1])
        ->assertSessionHasErrors('sha256');
    $fp = implode(':', array_fill(0, 32, 'AB'));
    asSuperAdmin()->post(route('network.android.settings.save'), ['package' => 'com.araild.mxconnect', 'sha256' => strtolower($fp), 'min' => 2, 'latest' => 3])
        ->assertSessionHasNoErrors();
    $this->getJson('/.well-known/assetlinks.json')->assertOk()
        ->assertJsonPath('0.target.package_name', 'com.araild.mxconnect')
        ->assertJsonPath('0.target.sha256_cert_fingerprints.0', $fp);

    // Ligne copiée telle quelle depuis Android Studio (signingReport) : préfixe ignoré, doublon retiré.
    asSuperAdmin()->post(route('network.android.settings.save'), ['package' => 'com.araild.mxconnect', 'sha256' => "SHA-256: $fp\nSHA256: $fp", 'min' => 2, 'latest' => 3])
        ->assertSessionHasNoErrors();
    $this->getJson('/.well-known/assetlinks.json')->assertJsonCount(1, '0.target.sha256_cert_fingerprints');
});

it('réserve les réglages et les documents au super-admin ; le modérateur lit les retours', function () {
    asSuperAdmin('moderator')->get(route('network.android.settings'))->assertForbidden();
    asSuperAdmin('moderator')->get(route('network.android.legal'))->assertForbidden();
    asSuperAdmin('moderator')->get(route('network.android.feedback'))->assertOk();
    asSuperAdmin()->get(route('network.android.settings'))->assertOk()->assertSee('com.araild.mxconnect');
    asSuperAdmin()->get(route('network.android.legal'))->assertOk()->assertSee('Modèle à compléter', false);
});

it('reçoit les retours bêta, même d’un compte qui n’a pas encore validé les documents', function () {
    $a = account('Awa', acceptLegal: false);
    $this->actingAs($a, 'member')->withSession(['app_version' => 7])
        ->post(route('social.feedback.store'), ['category' => 'bug', 'message' => 'Le bouton ne répond pas', 'screen' => 'Accueil'])->assertRedirect();
    $f = BetaFeedback::sole();
    expect([$f->public_account_id, $f->category, $f->app_version, $f->status])->toBe([$a->id, 'bug', 'Android 7', 'new']);

    $this->post(route('social.feedback.store'), ['category' => 'bug', 'message' => 'court'])->assertSessionHasErrors('message');
    asSuperAdmin()->get(route('network.android.feedback'))->assertOk()->assertSee('Le bouton ne répond pas');
});

it('ouvre les pages publiques exigées par le store et l’écran « Mon profil »', function () {
    $this->get(route('social.account.deletion'))->assertOk()->assertSee('Supprimer');
    $this->get(route('social.help'))->assertOk();
    $this->get(route('social.legal.show', ['type' => 'privacy']))->assertOk();
    $this->get(route('social.legal.show', ['type' => 'terms']))->assertNotFound();   // modèle non publié : jamais montré

    $a = account('Awa');
    $this->actingAs($a, 'member')->get(route('social.app.menu'))->assertOk()->assertSee('Mes publications')->assertSee('Déconnexion');
    $this->get(route('social.app.ready'))->assertOk();
    $this->get(route('social.legal.history'))->assertOk()->assertSee(legalDoc('privacy')->label());
});

it('propose d’inviter un ami par WhatsApp et les réseaux, sans identifiant dans le lien', function () {
    $this->get(route('social.invite'))->assertRedirect();   // réservé aux membres connectés

    $a = account('Awa');
    $link = route('social.register');
    $html = $this->actingAs($a, 'member')->get(route('social.invite'))->assertOk()
        ->assertSee('https://wa.me/?text=', false)
        ->assertSee('facebook.com/sharer/sharer.php?u=' . rawurlencode($link), false)
        ->assertSee('Awa vous invite')
        ->getContent();
    expect($html)->toContain(rawurlencode($link))->not->toContain(rawurlencode($link . '?'));   // lien nu, sans paramètre de suivi
    $this->get(route('social.app.menu'))->assertSee('Inviter un ami');
});
