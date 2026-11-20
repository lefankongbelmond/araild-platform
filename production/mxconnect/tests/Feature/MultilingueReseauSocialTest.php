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
