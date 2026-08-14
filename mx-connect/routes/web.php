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
use Illuminate\Support\Facades\Route;

/*
 | CENTRAL routes (network back-office + public). Served on the central domain.
 | Tenant routes live in routes/tenant.php (mapped by TenancyServiceProvider).
 */

Route::get('/', fn () => view('welcome'))->name('home');

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
