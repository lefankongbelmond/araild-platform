<?php

/*
 * Multilingue M3a (2026-11-30) : page « Paramètres » en cartes, fiche « Identité et langue »,
 * cadre du back-office et écrans de paramétrage traduits. VRAIE base de mutuelle (ou tables préfixées).
 */

use App\Models\Central\Locale;
use App\Models\Central\MemberLink;
use App\Models\Social\SocialNotification;
use App\Models\Tenant\Member;
use App\Support\Locales;
use App\Support\MutualIdentity;

beforeEach(function () {
    provisionTestMutual();
    staffUser('tresor@equipe.test', ['treasurer_accountant']);
    tenancy()->end();
    Locale::query()->update(['active' => true]);
    Locales::forgetCache();
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
    Locales::forgetCache();
    app()->setLocale('fr');
});

it('regroupe les paramètres en cartes, filtrées par les droits de chacun', function () {
    $h = $this->host;
    asAdmin()->get($h . '/parametres')->assertOk()
        ->assertSee('Paramètres de la mutuelle')->assertSee('Identité et langue')->assertSee('Équipe et rôles')
        ->assertSee('Règle de suspension')->assertSee($h . '/parametres/identite', false);

    // Le trésorier voit la page, mais seulement ses cartes : pas l'équipe, pas l'identité.
    as_('tresor@equipe.test')->get($h . '/parametres')->assertOk()
        ->assertSee('Règle de suspension')->assertDontSee('Équipe et rôles')->assertDontSee($h . '/parametres/identite', false);
    // … et la fiche lui est refusée par la matrice des droits, même en tapant l'adresse.
    as_('tresor@equipe.test')->get($h . '/parametres/identite')->assertForbidden();
    as_('tresor@equipe.test')->put($h . '/parametres/identite', ['locale' => 'en'])->assertForbidden();
});

it('enregistre la langue par défaut et les coordonnées, contrôlées côté serveur', function () {
    $h = $this->host;
    asAdmin()->put($h . '/parametres/identite', ['locale' => 'xx', 'phone' => 'abc'])->assertSessionHasErrors(['locale', 'phone']);

    asAdmin()->put($h . '/parametres/identite', ['locale' => 'en', 'phone' => '+237 6 99 00 11 22', 'email' => 'contact@equipe.test', 'address' => 'Rue 1, Yaoundé'])
        ->assertRedirect($h . '/parametres/identite');
    tenancy()->end();
    $en = Locale::where('code', 'en')->value('id');
    expect((int) $this->mutual->fresh()->locale_id)->toBe((int) $en);

    $line = $this->mutual->fresh()->run(fn () => MutualIdentity::line());
    expect($line)->toBe('Rue 1, Yaoundé · +237 6 99 00 11 22 · contact@equipe.test');
    // Trace dans le journal d'audit de la mutuelle.
    expect($this->mutual->run(fn () => \Illuminate\Support\Facades\DB::table('audit_logs')->where('action', 'identity_updated')->count()))->toBe(1);
});

it('affiche le back-office dans la langue de la mutuelle, sauf choix personnel', function () {
    $h = $this->host;
    $this->mutual->forceFill(['locale_id' => Locale::where('code', 'en')->value('id')])->save();

    // Personnel sans choix : langue de la mutuelle (anglais) — menu, cartes et écrans de paramétrage.
    asAdmin()->get($h . '/parametres')->assertOk()
        ->assertSee('lang="en"', false)->assertSee('Settings of the mutual')->assertSee('All settings')
        ->assertSee('Team and roles')->assertSee('Identity and language')->assertDontSee('Paramètres de la mutuelle');
    asAdmin()->get($h . '/equipe')->assertOk()->assertSee('Two-factor authentication');

    // Choix personnel : l'arabe, de droite à gauche.
    $this->mutual->run(fn () => \App\Models\Tenant\User::where('email', 'admin@equipe.test')->update(['locale' => 'ar']));
    tenancy()->end();
    asAdmin()->get($h . '/parametres')->assertOk()->assertSee('dir="rtl"', false)->assertSee('إعدادات التعاضدية');
});

it('écrit aux adhérents sans langue choisie dans la langue de leur mutuelle', function () {
    $this->mutual->forceFill(['locale_id' => Locale::where('code', 'pt')->value('id')])->save();
    $acc = account('Ines');                       // aucun choix de langue sur le compte
    $memberId = $this->mutual->run(fn () => Member::create(['member_code' => 'MUT-LANG', 'first_name' => 'Ines', 'last_name' => 'Test',
        'birth_date' => '1990-01-01', 'sex' => 'F', 'status' => 'active', 'joined_at' => '2026-01-01'])->id);
    tenancy()->end();
    MemberLink::create(['public_account_id' => $acc->id, 'mutual_id' => $this->mutual->id, 'member_id' => $memberId, 'member_code' => 'MUT-LANG']);

    $this->mutual->run(fn () => \App\Support\MemberNotifier::notify($memberId, \App\Support\Msg::of('Une prise en charge a été validée')));
    tenancy()->end();
    expect(SocialNotification::where('public_account_id', $acc->id)->latest('id')->value('title'))->toBe('Um pedido de cobertura foi validado');

    // Le choix du compte reste prioritaire.
    $acc->forceFill(['locale' => 'en'])->saveQuietly();
    $this->mutual->run(fn () => \App\Support\MemberNotifier::notify($memberId, \App\Support\Msg::of('Une prise en charge a été validée')));
    tenancy()->end();
    expect(SocialNotification::where('public_account_id', $acc->id)->latest('id')->value('title'))->toBe('A claim has been approved');
});
