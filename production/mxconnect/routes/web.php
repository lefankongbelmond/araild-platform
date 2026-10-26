<?php

use App\Http\Controllers\Network\LoginController;
use App\Http\Controllers\Network\MfaController;
use App\Http\Controllers\Network\MutualController;
use App\Http\Controllers\Network\DrTestController;
use App\Http\Controllers\Network\TenantHealthController;
use App\Http\Controllers\Network\ConsolidationController;
use App\Http\Controllers\Network\BillingController;
use App\Http\Controllers\Network\BillingPlanController;
use App\Http\Controllers\PublicDirectoryController;
use App\Http\Controllers\PublicComparatorController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\Network\ConfigController;
use App\Http\Controllers\Network\CurrencyController;
use App\Http\Controllers\Network\LocaleController;
use App\Http\Controllers\Network\CountryController;
use App\Http\Controllers\Network\PaymentProviderController;
use App\Http\Controllers\Social;
use Illuminate\Support\Facades\Route;

/*
 | CENTRAL routes (network back-office + public). Served on the central domain.
 | Tenant routes live in routes/tenant.php (mapped by TenancyServiceProvider).
 */

// Accueil : site public sur le domaine central ; sur un sous-domaine de
// mutuelle, renvoi vers l'espace de gestion de la mutuelle.
Route::get('/', function (\Illuminate\Http\Request $request) {
    if (! in_array($request->getHost(), config('tenancy.central_domains'), true)) {
        return redirect('/adherents');
    }
    return app(\App\Http\Controllers\HomeController::class)->index();
})->name('home');

// Page d'accueil de la plateforme : demande de démo, choix de l'espace de connexion.
Route::middleware('central.only')->group(function () {
    Route::post('demande-demo', [\App\Http\Controllers\HomeController::class, 'demo'])->name('home.demo')->middleware('throttle:social-contact');
    Route::get('se-connecter', [\App\Http\Controllers\HomeController::class, 'login'])->name('home.login');
    Route::post('se-connecter/mutuelle', [\App\Http\Controllers\HomeController::class, 'mutualLogin'])->name('home.login.mutual')->middleware('throttle:social-auth');
    // Accueil du réseau social pour les visiteurs (ancienne page d'accueil).
    Route::get('communaute', [Social\PageController::class, 'landing'])->name('social.landing');
});

// Ancienne page de présentation : remplacée par la nouvelle page d'accueil.
Route::get('/presentation', fn () => redirect()->route('home', [], 301))->name('welcome');

// Repli si l'hébergeur interdit les liens symboliques (public/storage) :
// sert storage/app/public. Inactif dès que le lien existe (Apache sert le fichier).
Route::get('storage/{path}', function (string $path) {
    $base = realpath(storage_path('app/public'));
    $file = realpath($base . '/' . $path);
    abort_unless($base && $file && str_starts_with($file, $base . DIRECTORY_SEPARATOR) && is_file($file), 404);

    return response()->file($file, ['Cache-Control' => 'public, max-age=86400']);
})->where('path', '.*')->name('storage.fallback');

// --- Network authentication ---
Route::middleware('guest:network')->group(function () {
    Route::get('reseau/connexion', [LoginController::class, 'show'])->name('network.login');
    Route::post('reseau/connexion', [LoginController::class, 'store'])
        ->middleware('throttle:5,1');   // rate limit login attempts
});

Route::middleware('auth:network')->group(function () {
    Route::post('reseau/deconnexion', [LoginController::class, 'destroy'])->name('network.logout');

    // MFA enrolment + challenge (not themselves behind the 'mfa' gate).
    Route::get('reseau/mfa/activation', [MfaController::class, 'enroll'])->name('mfa.enroll');
    Route::post('reseau/mfa/activation', [MfaController::class, 'confirm'])->name('mfa.confirm');
    Route::get('reseau/mfa/verification', [MfaController::class, 'challenge'])->name('mfa.challenge');
    Route::post('reseau/mfa/verification', [MfaController::class, 'verify'])->name('mfa.verify');

    // --- Network back-office (MFA-gated for sensitive roles) ---
    Route::middleware('mfa')->prefix('reseau')->name('network.')->group(function () {
        Route::get('mutuelles', [MutualController::class, 'index'])->name('mutuals.index');
        Route::get('mutuelles/creer', [MutualController::class, 'create'])->name('mutuals.create');
        Route::post('mutuelles', [MutualController::class, 'store'])->name('mutuals.store');
        Route::post('mutuelles/{mutual}/agrement', [MutualController::class, 'approve'])->name('mutuals.approve');
        Route::post('mutuelles/{mutual}/suspension', [MutualController::class, 'suspend'])->name('mutuals.suspend');

        // --- Phase 2 / Module 14: disaster-recovery drill log ---
        Route::get('pra', [DrTestController::class, 'index'])->name('dr.index');
        Route::post('pra', [DrTestController::class, 'store'])->name('dr.store');
        Route::get('pra/{drTest}', [DrTestController::class, 'show'])->name('dr.show');

        // --- Phase 3 / Module 15: operator tenant-health dashboard ---
        Route::get('sante-tenants', [TenantHealthController::class, 'index'])->name('health.index');

        // --- Phase 4 / Module 18: cross-mutual consolidation ---
        Route::get('consolidation', [ConsolidationController::class, 'index'])->name('consolidation.index');

        // --- Phase 4 / Module 20: SaaS billing ---
        Route::get('facturation', [BillingController::class, 'index'])->name('billing.index');
        Route::post('facturation/abonner', [BillingController::class, 'subscribe'])->name('billing.subscribe');
        Route::post('facturation/abonnements/{subscription}/facture', [BillingController::class, 'invoice'])->name('billing.invoice');
        Route::post('facturation/factures/{invoice}/payee', [BillingController::class, 'markPaid'])->name('billing.paid');
        Route::get('facturation/plans', [BillingPlanController::class, 'index'])->name('billing.plans');
        Route::post('facturation/plans', [BillingPlanController::class, 'store'])->name('billing.plans.store');
        Route::post('facturation/plans/{plan}/bascule', [BillingPlanController::class, 'toggle'])->name('billing.plans.toggle');

        // --- Réseau social : modération des signalements ---
        Route::get('moderation', [\App\Http\Controllers\Network\ModerationController::class, 'index'])->name('moderation.index');
        Route::get('moderation/masquees', [\App\Http\Controllers\Network\ModerationController::class, 'hiddenPosts'])->name('moderation.hidden');
        Route::post('moderation/{report}/masquer', [\App\Http\Controllers\Network\ModerationController::class, 'hide'])->name('moderation.hide');
        Route::post('moderation/{report}/classer', [\App\Http\Controllers\Network\ModerationController::class, 'dismiss'])->name('moderation.dismiss');
        Route::get('demandes-demo', [\App\Http\Controllers\Network\DemoRequestController::class, 'index'])->name('demos.index');
        Route::post('demandes-demo/{demo}/statut', [\App\Http\Controllers\Network\DemoRequestController::class, 'status'])->name('demos.status');
        Route::get('moderation/comptes', [\App\Http\Controllers\Network\ModerationController::class, 'accounts'])->name('moderation.accounts');
        Route::post('moderation/comptes/{account}/reactiver', [\App\Http\Controllers\Network\ModerationController::class, 'restoreAccount'])->name('moderation.accounts.restore')->whereNumber('account');
        Route::get('reseau-social/sponsorisations', [\App\Http\Controllers\Network\SocialAdminController::class, 'sponsorships'])->name('social.sponsorships');
        Route::post('reseau-social/sponsorisations/{sponsorship}', [\App\Http\Controllers\Network\SocialAdminController::class, 'decide'])->name('social.sponsorships.decide');
        Route::get('reseau-social/reglages', [\App\Http\Controllers\Network\SocialAdminController::class, 'settings'])->name('social.settings');
        Route::post('reseau-social/reglages', [\App\Http\Controllers\Network\SocialAdminController::class, 'saveSettings'])->name('social.settings.save');
        Route::get('moderation/pages', [\App\Http\Controllers\Network\ModerationController::class, 'pages'])->name('moderation.pages');
        Route::get('reseau-social/projets', [\App\Http\Controllers\Network\ProjectAdminController::class, 'index'])->name('social.projects');
        Route::post('reseau-social/resultats/{indicator}', [\App\Http\Controllers\Network\ProjectAdminController::class, 'verify'])->name('social.indicators.verify')->whereNumber('indicator');
        Route::post('reseau-social/projets/{project}/bascule', [\App\Http\Controllers\Network\ProjectAdminController::class, 'toggleProject'])->name('social.projects.toggle')->whereNumber('project');
        Route::post('reseau-social/opportunites/{opportunity}/bascule', [\App\Http\Controllers\Network\ProjectAdminController::class, 'toggleOpportunity'])->name('social.opportunities.toggle')->whereNumber('opportunity');
        Route::post('moderation/pages/{mutual}/bascule', [\App\Http\Controllers\Network\ModerationController::class, 'togglePage'])->name('moderation.pages.toggle');
        Route::post('moderation/publications/{post}/reafficher', [\App\Http\Controllers\Network\ModerationController::class, 'restore'])->name('moderation.restore');

        // --- Module 3: network configuration (data-driven, not hard-coded) ---
        Route::get('configuration', [ConfigController::class, 'index'])->name('config.index');

        Route::get('configuration/devises', [CurrencyController::class, 'index'])->name('config.currencies');
        Route::post('configuration/devises', [CurrencyController::class, 'store'])->name('config.currencies.store');
        Route::put('configuration/devises/{currency}', [CurrencyController::class, 'update'])->name('config.currencies.update');
        Route::post('configuration/devises/{currency}/statut', [CurrencyController::class, 'toggle'])->name('config.currencies.toggle');

        Route::get('configuration/langues', [LocaleController::class, 'index'])->name('config.locales');
        Route::post('configuration/langues', [LocaleController::class, 'store'])->name('config.locales.store');
        Route::put('configuration/langues/{locale}', [LocaleController::class, 'update'])->name('config.locales.update');
        Route::post('configuration/langues/{locale}/statut', [LocaleController::class, 'toggle'])->name('config.locales.toggle');

        Route::get('configuration/pays', [CountryController::class, 'index'])->name('config.countries');
        Route::post('configuration/pays', [CountryController::class, 'store'])->name('config.countries.store');
        Route::put('configuration/pays/{country}', [CountryController::class, 'update'])->name('config.countries.update');

        Route::get('configuration/prestataires-paiement', [PaymentProviderController::class, 'index'])->name('config.providers');
        Route::post('configuration/prestataires-paiement', [PaymentProviderController::class, 'store'])->name('config.providers.store');
        Route::put('configuration/prestataires-paiement/{provider}', [PaymentProviderController::class, 'update'])->name('config.providers.update');
        Route::post('configuration/prestataires-paiement/{provider}/statut', [PaymentProviderController::class, 'toggle'])->name('config.providers.toggle');
    });
});

// Annuaire public : remplacé par l'annuaire social des mutuelles (/mutuelles).
// Les anciens liens restent valides (redirection permanente, filtres conservés).
Route::get('/annuaire', fn (\Illuminate\Http\Request $r) => redirect()->route('social.mutuals', $r->query(), 301))->name('directory');

// Health probe (public, central) — load balancers / uptime monitors.
Route::get('/health', [HealthController::class, 'show'])->name('health');

// Public offer comparator (unauthenticated).
Route::get('/comparateur', [PublicComparatorController::class, 'index'])->name('comparator');

/*
 | RÉSEAU SOCIAL (domaine central). Données dans la base centrale, communes à
 | toutes les mutuelles. Garde « member » = comptes grand public (public_accounts).
 */
Route::middleware(['central.only', \App\Http\Middleware\SocialActiveMember::class, \App\Http\Middleware\SocialLoadGuard::class])->name('social.')->group(function () {
    // Comptes
    Route::middleware('guest:member')->group(function () {
        Route::get('compte/inscription', [Social\AuthController::class, 'showRegister'])->name('register');
        Route::post('compte/inscription', [Social\AuthController::class, 'register'])->name('register.store')->middleware('throttle:social-register');
        Route::get('compte/connexion', [Social\AuthController::class, 'showLogin'])->name('login');
        Route::post('compte/connexion', [Social\AuthController::class, 'login'])->name('login.store')->middleware('throttle:social-auth');
        Route::get('compte/mot-de-passe-oublie', [Social\PasswordResetController::class, 'request'])->name('password.request');
        Route::post('compte/mot-de-passe-oublie', [Social\PasswordResetController::class, 'email'])->name('password.email')->middleware('throttle:social-register');
        Route::get('compte/reinitialiser/{token}', [Social\PasswordResetController::class, 'edit'])->name('password.reset');
        Route::post('compte/reinitialiser', [Social\PasswordResetController::class, 'update'])->name('password.update')->middleware('throttle:social-auth');
    });
    Route::post('compte/deconnexion', [Social\AuthController::class, 'logout'])->name('logout');

    // Consultation publique
    Route::get('fil', [Social\FeedController::class, 'home'])->name('home');
    Route::get('publications', [Social\FeedController::class, 'all'])->name('all');
    Route::get('explorer', [Social\FeedController::class, 'explore'])->name('explore');
    Route::get('recherche', [Social\FeedController::class, 'search'])->name('search');
    Route::get('evenements', [Social\FeedController::class, 'events'])->name('events');
    Route::get('reels', [Social\FeedController::class, 'reels'])->name('reels');
    Route::get('sponsorise/{id}', [Social\AdvertiserController::class, 'click'])->name('sponsor.click')->whereNumber('id')->middleware('throttle:social-actions');
    Route::get('mutuelles', [Social\MutualController::class, 'index'])->name('mutuals');
    Route::get('mutuelles/{slug}', [Social\MutualController::class, 'show'])->name('mutual');
    Route::post('mutuelles/{slug}/contact', [Social\MutualController::class, 'contact'])->name('contact')->middleware('throttle:social-contact');
    Route::get('publications/{post}', [Social\PostController::class, 'show'])->name('post')->whereNumber('post');
    Route::get('u/{account}', [Social\AccountController::class, 'show'])->name('user')->whereNumber('account');
    Route::get('groupes', [Social\GroupController::class, 'index'])->name('groups');
    Route::get('groupes/{group}', [Social\GroupController::class, 'show'])->name('group');
    // Projets (résultats) et emplois / opportunités publiés par les pages
    Route::get('projets', [Social\ProjectController::class, 'index'])->name('projects');
    Route::get('projets/{project}', [Social\ProjectController::class, 'show'])->name('project');
    Route::get('opportunites', [Social\OpportunityController::class, 'index'])->name('opportunities');
    Route::get('opportunites/{opportunity}', [Social\OpportunityController::class, 'show'])->name('opportunity');
    Route::get('a-propos', [Social\PageController::class, 'about'])->name('about');
    Route::get('confidentialite', [Social\PageController::class, 'privacy'])->name('privacy');

    // Actions réservées aux comptes connectés
    Route::middleware('auth:member')->group(function () {
        Route::get('fil/abonnements', [Social\FeedController::class, 'following'])->name('following');
        Route::get('enregistrements', [Social\FeedController::class, 'saved'])->name('saved');
        Route::get('connexions', [Social\AccountController::class, 'connections'])->name('connections');
        Route::get('compte/parametres', [Social\AccountController::class, 'settings'])->name('settings');
        Route::post('compte/parametres', [Social\AccountController::class, 'update'])->name('settings.update');
        Route::post('compte/mot-de-passe', [Social\AccountController::class, 'password'])->name('password')->middleware('throttle:social-auth');

        // Livraison 2 : adhésion en ligne, messagerie, notifications
        Route::get('mutuelles/{slug}/adhesion', [Social\AdhesionController::class, 'create'])->name('adhesion.create');
        Route::post('mutuelles/{slug}/adhesion', [Social\AdhesionController::class, 'store'])->name('adhesion.store')->middleware('throttle:social-publish');
        Route::get('compte/adhesions', [Social\AdhesionController::class, 'index'])->name('adhesions');
        Route::get('compte/adhesions/{adhesion}', [Social\AdhesionController::class, 'show'])->name('adhesion');
        Route::post('compte/adhesions/{adhesion}/retirer', [Social\AdhesionController::class, 'withdraw'])->name('adhesion.withdraw');
        Route::post('compte/adhesions/{adhesion}/completer', [Social\AdhesionController::class, 'complete'])->name('adhesion.complete')->middleware('throttle:social-publish');
        Route::post('compte/adhesions/{adhesion}/message', [Social\AdhesionController::class, 'message'])->name('adhesion.message')->middleware('throttle:social-comment');
        Route::get('compte/adhesions/{adhesion}/pieces/{document}', [Social\AdhesionController::class, 'document'])->name('adhesion.document')->whereNumber('document');

        Route::get('messages', [Social\MessageController::class, 'index'])->name('messages');
        Route::get('messages/{conversation}', [Social\MessageController::class, 'show'])->name('messages.show')->whereNumber('conversation');
        Route::post('messages/{conversation}', [Social\MessageController::class, 'reply'])->name('messages.reply')->whereNumber('conversation')->middleware('throttle:social-comment');
        Route::post('mutuelles/{slug}/message', [Social\MessageController::class, 'start'])->name('messages.start')->middleware('throttle:social-publish');

        Route::post('groupes/{group}/rejoindre', [Social\GroupController::class, 'join'])->name('group.join')->middleware('throttle:social-actions');
        Route::post('groupes/{group}/quitter', [Social\GroupController::class, 'leave'])->name('group.leave');
        Route::post('groupes/{group}/publications', [Social\GroupController::class, 'post'])->name('group.post')->middleware('throttle:social-publish');
        Route::post('compte/supprimer', [Social\AccountController::class, 'destroy'])->name('account.destroy')->middleware('throttle:social-auth');

        Route::get('notifications', [Social\NotificationController::class, 'index'])->name('notifications');
        Route::get('notifications/{notification}', [Social\NotificationController::class, 'open'])->name('notifications.open')->whereNumber('notification');
        Route::post('notifications/tout-lire', [Social\NotificationController::class, 'readAll'])->name('notifications.read');

        // Pages créées par les membres
        Route::get('pages', [Social\OrgPageController::class, 'mine'])->name('pages.mine');
        Route::get('pages/creer', [Social\OrgPageController::class, 'create'])->name('pages.create');
        Route::post('pages', [Social\OrgPageController::class, 'store'])->name('pages.store')->middleware('throttle:social-publish');
        Route::get('pages/{slug}/modifier', [Social\OrgPageController::class, 'edit'])->name('pages.edit');
        Route::post('pages/{slug}/modifier', [Social\OrgPageController::class, 'update'])->name('pages.update');
        Route::post('pages/{slug}/supprimer', [Social\OrgPageController::class, 'destroy'])->name('pages.destroy');
        Route::post('pages/{slug}/gestionnaires', [Social\OrgPageController::class, 'addManager'])->name('pages.managers.add')->middleware('throttle:social-publish');
        Route::delete('pages/{slug}/gestionnaires/{admin}', [Social\OrgPageController::class, 'removeManager'])->name('pages.managers.remove')->whereNumber('admin');
        // « Je m'engage », « Soutenir ou devenir partenaire », candidatures (côté membre)
        Route::get('demandes/nouvelle', [Social\RequestController::class, 'create'])->name('request.create');
        Route::post('demandes', [Social\RequestController::class, 'store'])->name('request.store')->middleware('throttle:social-publish');
        Route::get('compte/demandes', [Social\RequestController::class, 'index'])->name('requests');
        Route::get('compte/demandes/{reference}', [Social\RequestController::class, 'show'])->name('request');
        Route::post('compte/demandes/{reference}/retirer', [Social\RequestController::class, 'withdraw'])->name('request.withdraw');
        Route::get('compte/demandes/{reference}/cv', [Social\RequestController::class, 'cv'])->name('request.cv');
        // Gestion d'une page : projets, opportunités, demandes reçues
        Route::prefix('pages/{slug}')->name('manage.')->controller(Social\PageManageController::class)->whereNumber(['project', 'indicator', 'update', 'opportunity'])->group(function () {
            Route::get('projets', 'projects')->name('projects');
            Route::get('projets/creer', 'createProject')->name('project.create');
            Route::post('projets', 'storeProject')->name('project.store')->middleware('throttle:social-publish');
            Route::get('projets/{project}/modifier', 'editProject')->name('project.edit');
            Route::post('projets/{project}/modifier', 'updateProject')->name('project.update');
            Route::post('projets/{project}/supprimer', 'destroyProject')->name('project.destroy');
            Route::post('projets/{project}/resultats', 'storeIndicator')->name('indicator.store');
            Route::post('projets/{project}/resultats/{indicator}', 'updateIndicator')->name('indicator.update');
            Route::post('projets/{project}/resultats/{indicator}/supprimer', 'destroyIndicator')->name('indicator.destroy');
            Route::post('projets/{project}/actualites', 'storeUpdate')->name('update.store')->middleware('throttle:social-publish');
            Route::post('projets/{project}/actualites/{update}/supprimer', 'destroyUpdate')->name('update.destroy');
            Route::get('opportunites', 'opportunities')->name('opportunities');
            Route::get('opportunites/creer', 'createOpportunity')->name('opportunity.create');
            Route::post('opportunites', 'storeOpportunity')->name('opportunity.store')->middleware('throttle:social-publish');
            Route::get('opportunites/{opportunity}/modifier', 'editOpportunity')->name('opportunity.edit');
            Route::post('opportunites/{opportunity}/modifier', 'updateOpportunity')->name('opportunity.update');
            Route::post('opportunites/{opportunity}/statut', 'availability')->name('opportunity.availability');
            Route::post('opportunites/{opportunity}/supprimer', 'destroyOpportunity')->name('opportunity.destroy');
            Route::get('demandes', 'requests')->name('requests');
            Route::get('demandes/{reference}', 'showRequest')->name('request');
            Route::post('demandes/{reference}/decision', 'decide')->name('request.decide');
            Route::get('demandes/{reference}/cv', 'requestCv')->name('request.cv');
        });
        // Compte annonceur et publications sponsorisées
        Route::get('annonceur', [Social\AdvertiserController::class, 'index'])->name('ads');
        Route::post('annonceur', [Social\AdvertiserController::class, 'save'])->name('ads.save')->middleware('throttle:social-publish');
        Route::get('publications/{post}/sponsoriser', [Social\AdvertiserController::class, 'create'])->name('sponsor.create')->whereNumber('post');
        Route::post('publications/{post}/sponsoriser', [Social\AdvertiserController::class, 'store'])->name('sponsor.store')->whereNumber('post')->middleware('throttle:social-publish');
        Route::post('annonceur/campagnes/{id}/paiement', [Social\AdvertiserController::class, 'payment'])->name('sponsor.payment')->whereNumber('id')->middleware('throttle:social-publish');
        Route::post('annonceur/campagnes/{id}/action', [Social\AdvertiserController::class, 'action'])->name('sponsor.action')->whereNumber('id');
        // Mentions (@) et envoi de vidéos par morceaux
        Route::get('mentions', [Social\PostController::class, 'mentionSearch'])->name('mentions')->middleware('throttle:social-search');
        Route::post('videos/envoi', [Social\PostController::class, 'videoStart'])->name('video.start')->middleware('throttle:social-upload');
        Route::post('videos/envoi/{upload}', [Social\PostController::class, 'videoChunk'])->name('video.chunk')->whereUuid('upload')->middleware('throttle:social-upload');

        Route::post('u/{account}/suivre', [Social\AccountController::class, 'follow'])->name('user.follow')->whereNumber('account')->middleware('throttle:social-actions');
        Route::post('u/{account}/signaler', [Social\AccountController::class, 'report'])->name('user.report')->whereNumber('account')->middleware('throttle:social-report');
        Route::post('mutuelles/{slug}/signaler', [Social\MutualController::class, 'report'])->name('mutual.report')->middleware('throttle:social-report');
        Route::post('mutuelles/{slug}/suivre', [Social\MutualController::class, 'follow'])->name('follow')->middleware('throttle:social-actions');
        Route::post('mutuelles/{slug}/masquer', [Social\MutualController::class, 'mute'])->name('mute');

        Route::middleware('throttle:social-actions')->whereNumber(['post', 'comment'])->group(function () {
            Route::post('publications', [Social\PostController::class, 'store'])->name('post.store')->middleware('throttle:social-publish');
            Route::delete('publications/{post}', [Social\PostController::class, 'destroy'])->name('post.destroy');
            Route::post('publications/{post}/reaction', [Social\PostController::class, 'react'])->name('post.react');
            Route::post('publications/{post}/enregistrer', [Social\PostController::class, 'save'])->name('post.save');
            Route::post('publications/{post}/partager', [Social\PostController::class, 'share'])->name('post.share')->middleware('throttle:social-publish');
            Route::post('publications/{post}/signaler', [Social\PostController::class, 'report'])->name('post.report')->middleware('throttle:social-report');
            Route::post('publications/{post}/commentaires', [Social\PostController::class, 'comment'])->name('comment.store')->middleware('throttle:social-comment');
            Route::delete('commentaires/{comment}', [Social\PostController::class, 'destroyComment'])->name('comment.destroy');
            Route::post('commentaires/{comment}/signaler', [Social\PostController::class, 'reportComment'])->name('comment.report')->middleware('throttle:social-report');
        });
    });
});
