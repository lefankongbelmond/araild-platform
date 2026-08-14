<?php

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use App\Http\Controllers\Tenant\AntennaController;
use App\Http\Controllers\Tenant\ActTypeController;
use App\Http\Controllers\Tenant\MedicineController;
use App\Http\Controllers\Tenant\GuaranteeController;
use App\Http\Controllers\Tenant\MemberController;
use App\Http\Controllers\Tenant\SubscriptionController;
use App\Http\Controllers\Tenant\ContributionController;
use App\Http\Controllers\Tenant\PaymentInitiationController;
use App\Http\Controllers\Tenant\CareClaimController;
use App\Http\Controllers\Tenant\PrestationController;
use App\Http\Controllers\Tenant\ClaimSettlementController;
use App\Http\Controllers\Tenant\AlertController;
use App\Http\Controllers\Tenant\TreasuryController;
use App\Http\Controllers\Tenant\AccountingController;
use App\Http\Controllers\Tenant\AuditController;
use App\Http\Controllers\Tenant\MemberImportController;
use App\Http\Controllers\Tenant\SolvencyController;
use App\Http\Controllers\Tenant\DataQualityController;
use App\Http\Controllers\Tenant\RiskController;
use App\Http\Controllers\Tenant\IncidentController;
use App\Http\Controllers\Tenant\ComplaintController;
use App\Http\Controllers\MemberApi\MemberAuthController;
use App\Http\Controllers\MemberApi\MemberApiController;
use App\Http\Controllers\MemberApi\PwaController;

/*
 | TENANT routes — served on each mutual's subdomain. The middleware switches the
 | default DB connection to that mutual's database for the whole request, so all
 | Eloquent queries below are physically confined to that mutual (isolation).
 */

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {

    // Mutual back-office (roles resolved inside the tenant DB).
    Route::middleware(['auth', 'mfa'])->group(function () {

        // --- Module 4: mutual parameters ---
        Route::get('parametres/antennes', [AntennaController::class, 'index'])->name('antennas.index');
        Route::post('parametres/antennes', [AntennaController::class, 'store'])->name('antennas.store');
        Route::put('parametres/antennes/{antenna}', [AntennaController::class, 'update'])->name('antennas.update');
        Route::delete('parametres/antennes/{antenna}', [AntennaController::class, 'destroy'])->name('antennas.destroy');

        Route::get('parametres/actes', [ActTypeController::class, 'index'])->name('act-types.index');
        Route::post('parametres/actes', [ActTypeController::class, 'store'])->name('act-types.store');
        Route::put('parametres/actes/{act_type}', [ActTypeController::class, 'update'])->name('act-types.update');

        Route::get('parametres/medicaments', [MedicineController::class, 'index'])->name('medicines.index');
        Route::post('parametres/medicaments', [MedicineController::class, 'store'])->name('medicines.store');
        Route::put('parametres/medicaments/{medicine}', [MedicineController::class, 'update'])->name('medicines.update');

        Route::get('garanties', [GuaranteeController::class, 'index'])->name('guarantees.index');
        Route::get('garanties/creer', [GuaranteeController::class, 'create'])->name('guarantees.create');
        Route::post('garanties', [GuaranteeController::class, 'store'])->name('guarantees.store');
        Route::get('garanties/{guarantee}', [GuaranteeController::class, 'show'])->name('guarantees.show');
        Route::post('garanties/{guarantee}/version', [GuaranteeController::class, 'newVersion'])->name('guarantees.version');

        // --- Module 5: members + dependents ---
        Route::get('adherents', [MemberController::class, 'index'])->name('members.index');

        // --- Module 11: member migration import ---
        Route::get('adherents/import', [MemberImportController::class, 'form'])->name('members.import.form');
        Route::post('adherents/import', [MemberImportController::class, 'import'])->name('members.import');
        Route::get('adherents/import/modele', [MemberImportController::class, 'template'])->name('members.import.template');
        Route::get('adherents/creer', [MemberController::class, 'create'])->name('members.create');
        Route::post('adherents', [MemberController::class, 'store'])->name('members.store');
        Route::get('adherents/{member}', [MemberController::class, 'show'])->name('members.show');
        Route::put('adherents/{member}', [MemberController::class, 'update'])->name('members.update');
        Route::post('adherents/{member}/suspension', [MemberController::class, 'suspend'])->name('members.suspend');
        Route::post('adherents/{member}/reactivation', [MemberController::class, 'reactivate'])->name('members.reactivate');
        Route::post('adherents/{member}/personnes-a-charge', [MemberController::class, 'addDependent'])->name('members.dependents.add');
        Route::delete('adherents/{member}/personnes-a-charge/{dependent}', [MemberController::class, 'removeDependent'])->name('members.dependents.remove');

        // --- Module 6: subscriptions (maker-checker) + digital card ---
        Route::get('souscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
        Route::get('souscriptions/creer', [SubscriptionController::class, 'create'])->name('subscriptions.create');
        Route::post('souscriptions', [SubscriptionController::class, 'store'])->name('subscriptions.store');
        Route::get('souscriptions/{subscription}', [SubscriptionController::class, 'show'])->name('subscriptions.show');
        Route::post('souscriptions/{subscription}/validation', [SubscriptionController::class, 'validateSubscription'])->name('subscriptions.validate');
        Route::get('adherents/{member}/carte', [SubscriptionController::class, 'card'])->name('members.card');

        // --- Module 7: contributions (arrears, cash payment, receipt) ---
        Route::get('cotisations', [ContributionController::class, 'index'])->name('contributions.index');
        Route::post('cotisations/{schedule}/paiement', [ContributionController::class, 'recordPayment'])->name('contributions.pay');
        Route::get('recus/{payment}', [ContributionController::class, 'receipt'])->name('contributions.receipt');

        // --- Module 8: Mobile Money initiation (facilitation) ---
        Route::post('cotisations/{schedule}/mobile-money', [PaymentInitiationController::class, 'initiate'])->name('payments.initiate');

        // --- Module 9: care claims chain (recours -> prestations -> settlement) ---
        Route::get('recours', [CareClaimController::class, 'index'])->name('claims.index');
        Route::get('recours/creer', [CareClaimController::class, 'create'])->name('claims.create');
        Route::post('recours', [CareClaimController::class, 'store'])->name('claims.store');
        Route::get('recours/{claim}', [CareClaimController::class, 'show'])->name('claims.show');
        Route::post('recours/{claim}/cloturer', [CareClaimController::class, 'close'])->name('claims.close');

        Route::post('recours/{claim}/prestations', [PrestationController::class, 'store'])->name('claims.prestations.store');
        Route::post('recours/{claim}/prestations/{prestation}/validation', [PrestationController::class, 'validatePrestation'])->name('claims.prestations.validate');
        Route::post('recours/{claim}/prestations/{prestation}/rejet', [PrestationController::class, 'reject'])->name('claims.prestations.reject');

        Route::post('recours/{claim}/remboursement', [ClaimSettlementController::class, 'requestReimbursement'])->name('claims.reimbursement.request');
        Route::post('recours/{claim}/remboursement/{reimbursement}/paiement', [ClaimSettlementController::class, 'payReimbursement'])->name('claims.reimbursement.pay');
        Route::post('recours/{claim}/facture', [ClaimSettlementController::class, 'createInvoice'])->name('claims.invoice.create');
        Route::post('recours/{claim}/facture/{invoice}/paiement', [ClaimSettlementController::class, 'payInvoice'])->name('claims.invoice.pay');

        // Anti-fraud alerts
        Route::get('alertes', [AlertController::class, 'index'])->name('alerts.index');
        Route::post('alertes/{alert}/traitement', [AlertController::class, 'clear'])->name('alerts.clear');

        // --- Module 10: treasury + SYSCOHADA accounting ---
        Route::get('tresorerie', [TreasuryController::class, 'dashboard'])->name('treasury.dashboard');
        Route::get('tresorerie/mouvements', [TreasuryController::class, 'movements'])->name('treasury.movements');
        Route::post('tresorerie/mouvements', [TreasuryController::class, 'storeMovement'])->name('treasury.movements.store');

        Route::get('comptabilite/plan', [AccountingController::class, 'chart'])->name('accounting.chart');
        Route::get('comptabilite/journal', [AccountingController::class, 'journal'])->name('accounting.journal');
        Route::post('comptabilite/poster-tresorerie', [AccountingController::class, 'postTreasury'])->name('accounting.post-treasury');
        Route::get('comptabilite/arrete-caisse', [AccountingController::class, 'closeForm'])->name('accounting.close.form');
        Route::post('comptabilite/arrete-caisse', [AccountingController::class, 'close'])->name('accounting.close');

        // --- Module 11: audit trail viewer ---
        Route::get('audit', [AuditController::class, 'index'])->name('audit.index');

        // --- Phase 2 / Module 12: actuarial & solvency + data quality ---
        Route::get('solvabilite', [SolvencyController::class, 'dashboard'])->name('solvency.dashboard');
        Route::get('qualite-donnees', [DataQualityController::class, 'index'])->name('quality.index');

        // --- Phase 2 / Module 13: governance registers ---
        Route::get('risques', [RiskController::class, 'index'])->name('risks.index');
        Route::post('risques', [RiskController::class, 'store'])->name('risks.store');
        Route::get('risques/{risk}', [RiskController::class, 'show'])->name('risks.show');
        Route::post('risques/{risk}/statut', [RiskController::class, 'updateStatus'])->name('risks.status');

        Route::get('incidents', [IncidentController::class, 'index'])->name('incidents.index');
        Route::post('incidents', [IncidentController::class, 'store'])->name('incidents.store');
        Route::get('incidents/{incident}', [IncidentController::class, 'show'])->name('incidents.show');
        Route::post('incidents/{incident}/statut', [IncidentController::class, 'updateStatus'])->name('incidents.status');

        Route::get('reclamations', [ComplaintController::class, 'index'])->name('complaints.index');
        Route::post('reclamations', [ComplaintController::class, 'store'])->name('complaints.store');
        Route::get('reclamations/{complaint}', [ComplaintController::class, 'show'])->name('complaints.show');
        Route::post('reclamations/{complaint}/statut', [ComplaintController::class, 'updateStatus'])->name('complaints.status');
    });

    // --- Module 11: member PWA shell + manifest (public) ---
    Route::get('espace-membre', [PwaController::class, 'shell'])->name('pwa.shell');
    Route::get('espace-membre/manifest.json', [PwaController::class, 'manifest'])->name('pwa.manifest');
    Route::get('espace-membre/fil', [PwaController::class, 'feed'])->name('pwa.feed');

    // --- Module 11: member API (Sanctum) ---
    Route::prefix('api/member')->middleware('throttle:member-api')->name('member.api.')->group(function () {
        Route::post('login', [MemberAuthController::class, 'login'])->name('login')->withoutMiddleware(['auth', 'mfa']);
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout',   [MemberAuthController::class, 'logout'])->name('logout');
            Route::get('me',        [MemberApiController::class, 'me'])->name('me');
            Route::get('card',      [MemberApiController::class, 'card'])->name('card');
            Route::get('schedules', [MemberApiController::class, 'schedules'])->name('schedules');
            Route::get('claims',    [MemberApiController::class, 'claims'])->name('claims');
            Route::post('schedules/{schedule}/pay', [MemberApiController::class, 'pay'])->name('pay');

            // --- Phase 5: member social feed (real-time via Reverb) ---
            Route::get('feed',                     [\App\Http\Controllers\MemberApi\FeedController::class, 'index'])->name('feed.index');
            Route::post('feed',                    [\App\Http\Controllers\MemberApi\FeedController::class, 'store'])->name('feed.store');
            Route::post('feed/{post}/like',        [\App\Http\Controllers\MemberApi\FeedController::class, 'like'])->name('feed.like');
            Route::post('feed/{post}/comment',     [\App\Http\Controllers\MemberApi\FeedController::class, 'comment'])->name('feed.comment');

            // Broadcasting auth for member channels — tenant-aware, Sanctum-guarded.
            Route::post('broadcasting/auth', fn (\Illuminate\Http\Request $request) => \Illuminate\Support\Facades\Broadcast::auth($request))->name('broadcasting.auth');
        });
    });

    // Provider-facing card verification (rights verdict only, no medical data).
    // Signed token in the query string; no auth required so a provider can scan it.
    Route::get('carte/verification', [SubscriptionController::class, 'verifyCard'])->name('members.card.verify');

    // Aggregator webhook target (idempotent, signature-verified).
    Route::post('webhooks/paiement/{provider}', [\App\Http\Controllers\PaymentWebhookController::class, 'handle'])
        ->middleware('throttle:webhooks')->name('payments.webhook')->withoutMiddleware(['auth', 'mfa']);
});
