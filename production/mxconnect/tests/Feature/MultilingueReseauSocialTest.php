<?php

/*
 * Multilingue M2a (2026-11-28) : paramètres du membre refaits en sections, réseau social
 * (écrans, menus, listes de libellés) traduit dans les 7 langues livrées.
 */

use App\Models\Central\Locale;
use App\Support\Locales;
use App\Support\TranslatableLabels;

beforeEach(function () {
    $this->c = rtrim((string) config('app.url'), '/');
    Locale::query()->update(['active' => true]);
    Locales::forgetCache();
});

afterEach(function () {
    Locales::forgetCache();
    app()->setLocale('fr');
});

it('déclare et traduit chaque libellé affiché depuis une liste PHP', function () {
    $labels = TranslatableLabels::all();
    expect(count($labels))->toBeGreaterThan(100);
    $fr = Locales::catalog('fr');
    foreach ($labels as $l) {
        expect(array_key_exists($l, $fr))->toBeTrue("absent de lang/fr.json : $l");
    }
    foreach (array_diff(Locales::supported(), ['fr']) as $code) {
        $t = Locales::catalog($code);
        foreach ($labels as $l) {
            expect(isset($t[$l]) && $t[$l] !== '')->toBeTrue("[$code] non traduit : $l");
        }
    }
});

it('présente les paramètres du membre en sections, sans changer l’enregistrement', function () {
    $a = account('Amina');
    $page = $this->actingAs($a, 'member')->get($this->c . '/compte/parametres')->assertOk();
    foreach (['#profil', '#compte', '#confidentialite', '#notifications', '#securite', '#langue', '#applications', '#donnees'] as $anchor) {
        $page->assertSee('href="' . $anchor . '"', false);
    }
    $page->assertSee('Paramètres du compte')->assertSee('/langue/en', false);

    // Même formulaire, mêmes règles qu'avant la refonte.
    $this->actingAs($a, 'member')->post($this->c . '/compte/parametres', [
        'first_name' => 'Amina', 'last_name' => 'Bello', 'email' => $a->email, 'city' => 'Garoua', 'profile_visibility' => 'private', 'email_notifications' => '0',
    ])->assertRedirect($this->c . '/compte/parametres');
    expect($a->fresh()->city)->toBe('Garoua')->and($a->fresh()->profile_visibility)->toBe('private');

    // En anglais : la page et le menu suivent la langue du compte.
    $a->forceFill(['locale' => 'en'])->saveQuietly();
    $this->actingAs($a->fresh(), 'member')->get($this->c . '/compte/parametres')->assertOk()
        ->assertSee('Account settings')->assertSee('Privacy')->assertSee('My data')->assertSee('Home')->assertDontSee('Paramètres du compte');
});

it('affiche le réseau social dans la langue choisie, y compris en arabe de droite à gauche', function () {
    $this->withUnencryptedCookie(Locales::COOKIE, 'ar')->get($this->c . '/a-propos')->assertOk()
        ->assertSee('dir="rtl"', false)->assertSee('حول MX-CONNECT')->assertSee('قواعد المجتمع');
    $this->withUnencryptedCookie(Locales::COOKIE, 'pt')->get($this->c . '/a-propos')->assertOk()
        ->assertSee('Sobre o MX-CONNECT')->assertSee('Regras da comunidade');
    // Libellés de listes (domaines) traduits à l'affichage.
    $this->withUnencryptedCookie(Locales::COOKIE, 'es')->get($this->c . '/decouvrir')->assertOk()->assertSee('Protección social');
    // Le français reste intact.
    $this->withUnencryptedCookie(Locales::COOKIE, 'fr')->get($this->c . '/a-propos')->assertSee('À propos de MX-CONNECT')->assertSee('Règles de la communauté');
});

/*
 * Multilingue M2b (2026-11-29) : phrases avec variables, messages du serveur, notifications et
 * e-mails dans la langue du destinataire, textes du JavaScript.
 */

it('envoie la notification dans la langue du destinataire, pas dans celle de son auteur', function () {
    $fr = account('Moussa');
    $en = account('Grace');
    $en->forceFill(['locale' => 'en'])->saveQuietly();

    // L'auteur navigue en français ; la destinataire a choisi l'anglais.
    $this->actingAs($fr, 'member')->post($this->c . '/u/' . $en->id . '/suivre')->assertRedirect();
    $n = \App\Models\Social\SocialNotification::where('public_account_id', $en->id)->latest('id')->first();
    expect($n)->not->toBeNull()->and($n->title)->toBe('Moussa Test followed your posts');

    // Et l'inverse : l'anglophone suit le francophone → notification en français.
    $this->actingAs($en->fresh(), 'member')->post($this->c . '/u/' . $fr->id . '/suivre')->assertRedirect();
    $n2 = \App\Models\Social\SocialNotification::where('public_account_id', $fr->id)->latest('id')->first();
    expect($n2->title)->toBe('Grace Test s’est abonné(e) à vos publications');

    // Variables traduites elles aussi (statut d'une demande) et texte libre laissé tel quel.
    expect(\App\Services\Social\NotificationService::text(\App\Support\Msg::of(':kind : acceptée', ['kind' => ['__' => 'Candidature']]), 'en'))
        ->toBe('Application: accepted')
        ->and(\App\Services\Social\NotificationService::text('Texte saisi par la mutuelle', 'ar'))->toBe('Texte saisi par la mutuelle');
});

it('traduit les messages du serveur et les phrases avec variables', function () {
    $a = account('Kofi');
    $a->forceFill(['locale' => 'en'])->saveQuietly();
    $this->actingAs($a->fresh(), 'member')->post($this->c . '/compte/parametres', [
        'first_name' => 'Kofi', 'last_name' => 'Test', 'email' => $a->email, 'profile_visibility' => 'public', 'email_notifications' => '1',
    ])->assertSessionHasNoErrors()->assertSessionHas('ok', 'Profile updated.');

    $this->actingAs($a->fresh(), 'member')->post($this->c . '/compte/mot-de-passe', [
        'current_password' => 'faux', 'password' => 'nouveau-mot-2026', 'password_confirmation' => 'nouveau-mot-2026',
    ])->assertSessionHasErrors(['current_password' => 'Current password incorrect.']);

    // Phrase avec variable dans une vue : zone de publication du fil.
    $this->actingAs($a->fresh(), 'member')->get($this->c . '/fil')->assertOk()->assertSee('What&#039;s new, Kofi?', false);

    // Pluriels.
    app()->setLocale('en');
    expect(trans_choice(':count comment|:count comments', 1, ['count' => 1]))->toBe('1 comment')
        ->and(trans_choice(':count commentaire|:count commentaires', 3, ['count' => 3]))->toBe('3 comments');
    app()->setLocale('fr');
    expect(trans_choice(':count commentaire|:count commentaires', 0, ['count' => 0]))->toBe('0 commentaire');
});

it('déclare chaque texte du JavaScript et l’envoie traduit à la page', function () {
    $root = dirname(base_path());
    foreach (['js/mx-social.js', 'js/mx-social-plus.js'] as $js) {
        preg_match_all("/\\bT\\('((?:[^'\\\\]|\\\\.)*)'/u", file_get_contents($root . '/' . $js), $m);
        expect($m[1])->not->toBeEmpty();
        foreach (array_unique($m[1]) as $key) {
            expect(in_array($key, \App\Support\JsTranslations::SOCIAL, true))->toBeTrue("$js : T('$key') absent de JsTranslations::SOCIAL");
        }
    }

    $a = account('Lina');
    $a->forceFill(['locale' => 'pt'])->saveQuietly();
    $html = $this->actingAs($a->fresh(), 'member')->get($this->c . '/fil')->assertOk()->getContent();
    expect($html)->toContain('id="mx-i18n"');
    preg_match('#<script type="application/json" id="mx-i18n">(.*?)</script>#s', $html, $mm);
    $dict = json_decode($mm[1], true);
    expect($dict['Suivre'] ?? null)->toBe(__('Suivre', [], 'pt'))->and($dict['Lien copié'] ?? null)->toBe('Ligação copiada');

    // En français, rien n'est envoyé : le script garde ses textes.
    $fr = account('Paul');
    $this->actingAs($fr, 'member')->get($this->c . '/fil')->assertOk()->assertDontSee('id="mx-i18n"', false);
});
