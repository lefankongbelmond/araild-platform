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

/*
 | Social network layer (public, central) — Module 1 to 5. Read pages are public;
 | every write goes through `auth:member` (public accounts) and is re-checked in the
 | Social services. Fixed paths (…/creer) are declared before the {slug} routes.
 */
Route::get('/', [Social\HomeController::class, 'index'])->name('home');
Route::get('fil', [Social\FeedController::class, 'index'])->name('feed');
Route::get('decouvrir', [Social\DiscoverController::class, 'index'])->name('discover');

Route::middleware('guest:member')->group(function () {
    Route::get('connexion', [Social\AuthController::class, 'showLogin'])->name('login');
    Route::post('connexion', [Social\AuthController::class, 'login'])->middleware('throttle:login');
    Route::get('inscription', [Social\AuthController::class, 'showRegister'])->name('register');
    Route::post('inscription', [Social\AuthController::class, 'register'])->middleware('throttle:login');
});

Route::middleware('auth:member')->group(function () {
    Route::post('deconnexion', [Social\AuthController::class, 'logout'])->name('logout');

    Route::middleware('throttle:social-write')->group(function () {
        Route::post('publications', [Social\FeedController::class, 'store'])->name('posts.store');
        Route::delete('publications/{post}', [Social\FeedController::class, 'destroy'])->name('posts.destroy');
        Route::post('publications/{post}/jaime', [Social\FeedController::class, 'like'])->name('posts.like');
        Route::post('publications/{post}/commentaires', [Social\FeedController::class, 'comment'])->name('posts.comments.store');
        Route::delete('commentaires/{comment}', [Social\FeedController::class, 'destroyComment'])->name('comments.destroy');

        Route::post('organisations', [Social\OrganizationController::class, 'store'])->name('organizations.store');
        Route::put('organisations/{organization:slug}', [Social\OrganizationController::class, 'update'])->name('organizations.update');
        Route::post('organisations/{organization:slug}/suivre', [Social\OrganizationController::class, 'follow'])->name('organizations.follow');
        Route::delete('organisations/{organization:slug}/suivre', [Social\OrganizationController::class, 'unfollow'])->name('organizations.unfollow');
        Route::post('organisations/{organization:slug}/je-m-engage', [Social\RequestController::class, 'engage'])->name('organizations.engage');
        Route::post('organisations/{organization:slug}/soutenir', [Social\RequestController::class, 'support'])->name('organizations.support');
        Route::post('organisations/{organization:slug}/projets', [Social\ProjectController::class, 'store'])->name('projects.store');

        Route::put('projets/{project:slug}', [Social\ProjectController::class, 'update'])->name('projects.update');
        Route::post('projets/{project:slug}/indicateurs', [Social\ProjectController::class, 'storeIndicator'])->name('projects.indicators.store');
        Route::post('projets/{project:slug}/actualites', [Social\ProjectController::class, 'storeUpdate'])->name('projects.updates.store');

        Route::post('mon-espace/demandes/{kind}/{id}/retrait', [Social\MySpaceController::class, 'withdraw'])
            ->whereIn('kind', ['engagement', 'support'])->whereNumber('id')->name('my.requests.withdraw');
        Route::post('mon-espace/organisations/{organization:slug}/demandes/{kind}/{id}', [Social\MySpaceController::class, 'respond'])
            ->whereIn('kind', ['engagement', 'support'])->whereNumber('id')->name('my.organizations.respond');
    });

    Route::get('organisations/creer', [Social\OrganizationController::class, 'create'])->name('organizations.create');
    Route::get('organisations/{organization:slug}/modifier', [Social\OrganizationController::class, 'edit'])->name('organizations.edit');
    Route::get('organisations/{organization:slug}/je-m-engage', [Social\RequestController::class, 'engageForm'])->name('organizations.engage.form');
    Route::get('organisations/{organization:slug}/soutenir', [Social\RequestController::class, 'supportForm'])->name('organizations.support.form');
    Route::get('organisations/{organization:slug}/projets/creer', [Social\ProjectController::class, 'create'])->name('projects.create');
    Route::get('projets/{project:slug}/modifier', [Social\ProjectController::class, 'edit'])->name('projects.edit');

    Route::get('mon-espace/demandes', [Social\MySpaceController::class, 'requests'])->name('my.requests');
    Route::get('mon-espace/organisations', [Social\MySpaceController::class, 'organizations'])->name('my.organizations');
    Route::get('mon-espace/organisations/{organization:slug}/demandes', [Social\MySpaceController::class, 'inbox'])->name('my.organizations.inbox');
    Route::get('mon-espace/notifications', [Social\MySpaceController::class, 'notifications'])->name('my.notifications');
    Route::post('mon-espace/notifications/lues', [Social\MySpaceController::class, 'markNotificationsRead'])->name('my.notifications.read');
});

Route::get('publications/{post}', [Social\FeedController::class, 'show'])->whereNumber('post')->name('posts.show');
Route::get('organisations/{organization:slug}', [Social\OrganizationController::class, 'show'])->name('organizations.show');
Route::get('projets/{project:slug}', [Social\ProjectController::class, 'show'])->name('projects.show');

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

// Public network directory (unauthenticated).
Route::get('/annuaire', [PublicDirectoryController::class, 'index'])->name('directory');

// Health probe (public, central) — load balancers / uptime monitors.
Route::get('/health', [HealthController::class, 'show'])->name('health');

// Public offer comparator (unauthenticated).
Route::get('/comparateur', [PublicComparatorController::class, 'index'])->name('comparator');
