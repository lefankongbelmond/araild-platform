<?php

/*
 * Multilingue M1 (2026-11-27) : choix de la langue (compte, cookie, mutuelle, navigateur),
 * sélecteur, sens d'écriture, formats, catalogue complet dans chaque langue livrée.
 */

use App\Models\Central\Currency;
use App\Models\Central\Locale;
use App\Support\Locales;

beforeEach(function () {
    $this->c = rtrim((string) config('app.url'), '/');
    Locales::forgetCache();
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
    Locales::forgetCache();
    app()->setLocale('fr');
});

function i18nAdmin(): \Illuminate\Foundation\Testing\TestCase
{
    \Spatie\Permission\Models\Role::findOrCreate('super_admin', 'network');
    $u = \App\Models\Central\NetworkUser::factory()->create(['mfa_enabled' => true]);
    $u->assignRole('super_admin');

    return test()->actingAs($u, 'network')->withSession(['mfa_passed' => true]);
}

function activate(string ...$codes): void
{
    Locale::whereIn('code', $codes)->update(['active' => true]);
    Locales::forgetCache();
}

it('garde le français par défaut et suit le navigateur parmi les langues activées', function () {
    $this->get($this->c . '/a-propos')->assertOk()->assertSee('<html lang="fr" dir="ltr"', false);
    // Anglais activé : le navigateur anglophone l'obtient ; portugais non activé : français.
    $this->withHeader('Accept-Language', 'en-GB,en;q=0.9')->get($this->c . '/a-propos')->assertSee('<html lang="en" dir="ltr"', false);
    $this->withHeader('Accept-Language', 'pt-PT')->get($this->c . '/a-propos')->assertSee('<html lang="fr"', false);
    // Le sélecteur ne propose que les langues activées.
    $page = $this->withHeader('Accept-Language', 'fr')->get($this->c . '/a-propos')->assertSee('mx-lng', false)->assertSee('/langue/en', false);
    $page->assertDontSee('/langue/pt', false);
});

it('mémorise le choix : cookie avant connexion, puis sur le compte du membre', function () {
    $this->get($this->c . '/langue/pt')->assertNotFound();          // langue non activée
    activate('es');
    $res = $this->withHeader('referer', $this->c . '/a-propos')->get($this->c . '/langue/es');
    $res->assertRedirect($this->c . '/a-propos')->assertCookie(Locales::COOKIE, 'es', false);
    $this->withUnencryptedCookie(Locales::COOKIE, 'es')->get($this->c . '/a-propos')->assertSee('<html lang="es" dir="ltr"', false);

    // Membre connecté : la préférence est enregistrée sur son compte et le suit sans cookie.
    $a = account('Amina');
    $this->actingAs($a, 'member')->get($this->c . '/langue/es')->assertRedirect();
    expect($a->fresh()->locale)->toBe('es');
    $this->flushSession();
    $this->actingAs($a->fresh(), 'member')->get($this->c . '/a-propos')->assertSee('<html lang="es"', false);

    // Une langue désactivée ensuite n'est plus appliquée : retour au français.
    Locale::where('code', 'es')->update(['active' => false]);
    Locales::forgetCache();
    $this->actingAs($a->fresh(), 'member')->get($this->c . '/a-propos')->assertSee('<html lang="fr"', false);
});

it('applique la langue de la mutuelle à son personnel, sauf choix personnel', function () {
    provisionTestMutual();
    $h = $this->host;
    asAdmin()->get($h . '/adherents')->assertOk()->assertSee('<html lang="fr" dir="ltr"', false);

    // Langue de la mutuelle : anglais.
    $this->mutual->update(['locale_id' => Locale::where('code', 'en')->value('id')]);
    \Illuminate\Support\Facades\Cache::flush();
    tenancy()->end();
    asAdmin()->get($h . '/adherents')->assertSee('<html lang="en" dir="ltr"', false);

    // Choix personnel du membre du personnel (route propre au sous-domaine) : enregistré sur son compte.
    activate('ar');
    asAdmin()->get($h . '/interface/langue/ar')->assertRedirect();
    expect(tenantUser('admin@equipe.test')->locale)->toBe('ar');
    tenancy()->end();
    asAdmin()->get($h . '/adherents')->assertSee('<html lang="ar" dir="rtl"', false)->assertSee('/css/mx-rtl.css', false);
    asAdmin()->get($h . '/interface/langue/xx')->assertNotFound();
});

it('formate les montants selon la langue, sans jamais convertir', function () {
    $cur = new Currency(['code' => 'XAF', 'symbol' => 'FCFA', 'minor_unit' => 0]);
    app()->setLocale('fr');
    expect($cur->format(5160000))->toBe('5 160 000 FCFA');
    app()->setLocale('en');
    expect($cur->format(5160000))->toBe('5,160,000 FCFA');
    app()->setLocale('pt');
    expect($cur->format(5160000))->toBe('5.160.000 FCFA');
});

it('traduit les messages de validation dans la langue choisie', function () {
    activate('es', 'ar');
    $this->withUnencryptedCookie(Locales::COOKIE, 'es')->post($this->c . '/compte/connexion', [])->assertSessionHasErrors();
    $errors = session('errors')->all();
    expect(implode(' ', $errors))->toContain('obligatorio');
    app()->setLocale('ar');
    expect(__('validation.required', ['attribute' => 'x']))->toBe('الحقل x مطلوب.');
    app()->setLocale('ha');
    expect(__('auth.failed'))->toStartWith('Waɗannan');
});

it('réserve la gestion des langues au super-admin, garde le français toujours actif', function () {
    i18nAdmin()->get($this->c . '/reseau/configuration/langues')->assertOk()->assertSee('العربية')->assertSee('Kiswahili')
        ->assertSee('Textes déjà traduisibles traduits')->assertSee('droite à gauche');
    $fr = Locale::where('code', 'fr')->first();
    i18nAdmin()->post($this->c . "/reseau/configuration/langues/{$fr->id}/statut")->assertSessionHas('error');
    expect($fr->fresh()->active)->toBeTrue();
    $ar = Locale::where('code', 'ar')->first();
    i18nAdmin()->post($this->c . "/reseau/configuration/langues/{$ar->id}/statut")->assertSessionHas('success');
    expect($ar->fresh()->active)->toBeTrue()->and(Locales::active())->toContain('ar');
});

it('livre un catalogue complet dans chaque langue, avec les mêmes variables', function () {
    $source = Locales::entries('fr');
    expect($source)->not->toBeEmpty();
    $vars = fn (string $s) => collect(preg_match_all('/:([a-z_]+)/i', $s, $m) ? $m[1] : [])->unique()->sort()->values()->all();   // ensemble : un pluriel peut avoir plus de formes (arabe : 6)
    foreach (array_diff(Locales::supported(), ['fr']) as $code) {
        $t = Locales::entries($code);
        foreach ($source as $key => $text) {
            if (str_starts_with($key, 'mxconnect.')) {
                continue;   // ancien back-office : traduit au fil des livraisons M2 à M5
            }
            expect(array_key_exists($key, $t))->toBeTrue("[$code] manque : $key");
            expect($vars($t[$key]))->toBe($vars($text), "[$code] variables différentes : $key");
        }
    }
});

it('déclare dans le catalogue français chaque texte traduisible écrit dans le code', function () {
    $catalog = Locales::catalog('fr');
    $missing = [];
    foreach (['app', 'resources/views', 'routes'] as $dir) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($dir), FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (! str_ends_with($file->getFilename(), '.php')) {
                continue;
            }
            preg_match_all("/(?:__|@lang|trans|trans_choice|Msg::of)\\(\\s*(['\"])((?:\\\\.|(?!\\1).)*)\\1/u", file_get_contents($file->getPathname()), $m);
            foreach ($m[2] as $key) {
                $key = stripslashes($key);
                if (preg_match('/^[A-Za-z0-9_\-]+(\.[A-Za-z0-9_\-]*)+$/', $key)) {
                    continue;   // clé de groupe (mxconnect.x, validation.x…)
                }
                if (! array_key_exists($key, $catalog)) {
                    $missing[] = $key . '  (' . str_replace(base_path() . '/', '', $file->getPathname()) . ')';
                }
            }
        }
    }
    expect($missing)->toBe([]);
});
