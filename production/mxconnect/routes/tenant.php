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
use App\Http\Controllers\Tenant\AuthController;

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

    // --- Connexion du personnel de la mutuelle (guard 'web', base tenant) ---
    Route::middleware('guest:web')->group(function () {
        Route::get('connexion', [AuthController::class, 'showLogin'])->name('tenant.login');
        Route::post('connexion', [AuthController::class, 'login'])->middleware('throttle:5,1');
    });
    Route::middleware('auth:web')->group(function () {
        Route::post('deconnexion', [AuthController::class, 'logout'])->name('tenant.logout');
        Route::get('mfa/activation', [AuthController::class, 'enroll'])->name('tenant.mfa.enroll');
        Route::post('mfa/activation', [AuthController::class, 'confirm'])->name('tenant.mfa.confirm');
        Route::get('mfa/verification', [AuthController::class, 'challenge'])->name('tenant.mfa.challenge');
        Route::post('mfa/verification', [AuthController::class, 'verify'])->name('tenant.mfa.verify');
    });

    // Mutual back-office (roles resolved inside the tenant DB).
    // Ordre : compte désactivé / mot de passe provisoire, puis double authentification, puis
    // matrice des droits (2026-10-31) : TenantRouteAccess applique App\Support\TenantAccess à CHAQUE route.
    Route::middleware(['auth:web', \App\Http\Middleware\TenantAccountGuard::class, 'mfa', \App\Http\Middleware\TenantRouteAccess::class])->group(function () {

        // --- Signaler un incident ou un risque (tout le personnel) ---
        Route::get('signaler', [\App\Http\Controllers\Tenant\SignalController::class, 'create'])->name('signal.create');

        // --- Prestataires conventionnés (registre) ---
        Route::prefix('parametres/prestataires')->name('providers.')->controller(\App\Http\Controllers\Tenant\ProviderController::class)
            ->whereNumber('convention')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('ajouter', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::get('{convention}', 'show')->name('show');
                Route::get('{convention}/modifier', 'edit')->name('edit');
                Route::post('{convention}/modifier', 'update')->name('update');
                Route::post('{convention}/activation', 'toggle')->name('toggle');
            });

        // --- Portail des prestataires de soins (rôle « provider » uniquement) ---
        Route::prefix('prestataire')->name('portal.')->controller(\App\Http\Controllers\Tenant\ProviderPortalController::class)
            ->whereNumber(['check', 'claim'])->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('verifier', 'verify')->name('verify')->middleware('throttle:30,1');
                Route::get('declarer/{check}', 'declare')->name('declare');
                Route::post('declarer/{check}', 'storeDeclaration')->name('declare.store');
                Route::get('prises-en-charge', 'claims')->name('claims');
                Route::get('factures', 'invoices')->name('invoices');
                Route::post('factures/{claim}', 'requestPayment')->name('invoices.store');
                Route::post('factures/{invoice}/piece', 'attachDocument')->name('invoices.document.store')->whereNumber('invoice');
                Route::get('factures/{invoice}/piece', 'document')->name('invoices.document')->whereNumber('invoice');
            });

        // --- Mon mot de passe + Équipe et rôles (2026-10-30) ---
        Route::get('mon-mot-de-passe', [\App\Http\Controllers\Tenant\AccountController::class, 'edit'])->name('account.password');
        Route::post('mon-mot-de-passe', [\App\Http\Controllers\Tenant\AccountController::class, 'update'])->name('account.password.update')->middleware('throttle:5,1');
        Route::prefix('equipe')->name('team.')->controller(\App\Http\Controllers\Tenant\TeamController::class)->whereNumber('user')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('roles-et-droits', 'roles')->name('roles');
            Route::get('ajouter', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('{user}/modifier', 'edit')->name('edit');
            Route::post('{user}/modifier', 'update')->name('update');
            Route::post('{user}/activation', 'toggle')->name('toggle');
            Route::post('{user}/mot-de-passe', 'resetPassword')->name('password');
            Route::post('{user}/double-authentification', 'resetMfa')->name('mfa');
        });

        // --- Réseau social : page publique et publications de la mutuelle ---
        Route::prefix('reseau-social')->name('social-presence.')->controller(\App\Http\Controllers\Tenant\SocialPresenceController::class)
            ->whereNumber(['post', 'comment', 'message'])->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('page', 'editProfile')->name('profile');
                Route::post('page', 'updateProfile')->name('profile.update');
                Route::post('page/gestionnaires', 'addManager')->name('managers.add');
                Route::delete('page/gestionnaires/{admin}', 'removeManager')->name('managers.remove')->whereNumber('admin');
                Route::get('publications/creer', 'create')->name('create');
                Route::post('publications', 'store')->name('store');
                Route::get('publications/{post}/modifier', 'edit')->name('edit');
                Route::put('publications/{post}', 'update')->name('update');
                Route::post('publications/{post}/statut', 'status')->name('status');
                Route::delete('publications/{post}', 'destroy')->name('destroy');
                Route::get('publications/{post}/commentaires', 'comments')->name('comments');
                Route::post('commentaires/{comment}/masquer', 'hideComment')->name('comments.hide');
                Route::get('messages', 'messages')->name('messages');
                Route::post('messages/{message}/statut', 'messageStatus')->name('messages.status');
            });

        // --- Réseau social (livraison 3) : groupes animés par la mutuelle ---
        Route::prefix('reseau-social/groupes')->name('social-groups.')->controller(\App\Http\Controllers\Tenant\GroupAdminController::class)
            ->whereNumber(['group', 'member', 'post'])->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('creer', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::get('{group}/modifier', 'edit')->name('edit');
                Route::put('{group}', 'update')->name('update');
                Route::post('{group}/archiver', 'archive')->name('archive');
                Route::get('{group}/membres', 'members')->name('members');
                Route::post('{group}/membres/{member}', 'decideMember')->name('members.decide');
                Route::get('{group}/publications', 'posts')->name('posts');
                Route::post('{group}/publications/{post}/moderer', 'togglePost')->name('posts.toggle');
            });

        // --- Réseau social (livraison 2) : adhésions en ligne et messagerie ---
        Route::prefix('adhesions-en-ligne')->name('online-adhesions.')->controller(\App\Http\Controllers\Tenant\OnlineAdhesionController::class)
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('{reference}', 'show')->name('show');
                Route::post('{reference}/statut', 'decide')->name('decide');
                Route::get('{reference}/pieces/{document}', 'document')->name('document')->whereNumber('document');
                Route::post('{reference}/preremplir', 'prefill')->name('prefill');
                Route::post('{reference}/relier', 'link')->name('link');
            });
        // --- Réseau social (2026-10-26) : projets, emplois et opportunités, demandes reçues ---
        Route::prefix('projets-opportunites')->name('engagement.')->controller(\App\Http\Controllers\Tenant\EngagementController::class)
            ->whereNumber(['project', 'indicator', 'opportunity'])->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('demandes/{reference}', 'showRequest')->name('requests.show');
                Route::post('demandes/{reference}/decision', 'decide')->name('requests.decide');
                Route::get('demandes/{reference}/cv', 'cv')->name('requests.cv');
                Route::get('projets/creer', 'createProject')->name('projects.create');
                Route::post('projets', 'storeProject')->name('projects.store');
                Route::get('projets/{project}/modifier', 'editProject')->name('projects.edit');
                Route::post('projets/{project}/modifier', 'updateProject')->name('projects.update');
                Route::post('projets/{project}/supprimer', 'destroyProject')->name('projects.destroy');
                Route::post('projets/{project}/resultats', 'storeIndicator')->name('indicators.store');
                Route::post('projets/{project}/resultats/{indicator}', 'updateIndicator')->name('indicators.update');
                Route::post('projets/{project}/resultats/{indicator}/supprimer', 'destroyIndicator')->name('indicators.destroy');
                Route::post('projets/{project}/actualites', 'storeUpdate')->name('updates.store');
                Route::post('projets/{project}/documents', 'storeDocuments')->name('documents.store');
                Route::post('projets/{project}/documents/{document}/supprimer', 'destroyDocument')->name('documents.destroy')->whereNumber('document');
                Route::get('opportunites/creer', 'createOpportunity')->name('opportunities.create');
                Route::post('opportunites', 'storeOpportunity')->name('opportunities.store');
                Route::get('opportunites/{opportunity}/modifier', 'editOpportunity')->name('opportunities.edit');
                Route::post('opportunites/{opportunity}/modifier', 'updateOpportunity')->name('opportunities.update');
                Route::post('opportunites/{opportunity}/statut', 'availability')->name('opportunities.availability');
                Route::post('opportunites/{opportunity}/supprimer', 'destroyOpportunity')->name('opportunities.destroy');
            });
        Route::prefix('messagerie')->name('conversations.')->controller(\App\Http\Controllers\Tenant\ConversationController::class)
            ->whereNumber('conversation')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('{conversation}', 'show')->name('show');
                Route::post('{conversation}', 'reply')->name('reply');
                Route::post('{conversation}/statut', 'toggle')->name('toggle');
            });

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
        Route::delete('parametres/medicaments/{medicine}', [MedicineController::class, 'destroy'])->name('medicines.destroy');

        // --- Gestion avancée : référentiels, facturation, budget, cartes, notifications ---
        Route::get('parametres/referentiels/{kind}', [\App\Http\Controllers\Tenant\CatalogImportController::class, 'show'])->name('catalog.import')->where('kind', 'actes|medicaments|garanties');
        Route::post('parametres/referentiels/{kind}', [\App\Http\Controllers\Tenant\CatalogImportController::class, 'store'])->name('catalog.import.store')->where('kind', 'actes|medicaments|garanties');
        Route::get('facturation', [\App\Http\Controllers\Tenant\BillingController::class, 'dashboard'])->name('billing.dashboard');
        Route::get('facturation/quote-part', [\App\Http\Controllers\Tenant\BillingController::class, 'quote'])->name('billing.quote');
        Route::post('facturation/quote-part', [\App\Http\Controllers\Tenant\BillingController::class, 'computeQuote'])->name('billing.quote.compute');
        Route::get('budget', [\App\Http\Controllers\Tenant\BudgetController::class, 'index'])->name('budget.index');
        Route::post('budget', [\App\Http\Controllers\Tenant\BudgetController::class, 'create'])->name('budget.create');
        Route::post('budget/{budget}/lignes', [\App\Http\Controllers\Tenant\BudgetController::class, 'storeLine'])->name('budget.lines.store');
        Route::put('budget/{budget}/lignes', [\App\Http\Controllers\Tenant\BudgetController::class, 'updateLines'])->name('budget.lines.update');
        Route::delete('budget/{budget}/lignes/{line}', [\App\Http\Controllers\Tenant\BudgetController::class, 'destroyLine'])->name('budget.lines.destroy');
        Route::post('budget/{budget}/adoption', [\App\Http\Controllers\Tenant\BudgetController::class, 'adopt'])->name('budget.adopt');
        Route::get('cartes', [\App\Http\Controllers\Tenant\MemberCardController::class, 'index'])->name('cards.index');
        Route::get('cartes/impression', [\App\Http\Controllers\Tenant\MemberCardController::class, 'batch'])->name('cards.batch');
        Route::post('cartes/imprimees', [\App\Http\Controllers\Tenant\MemberCardController::class, 'markPrinted'])->name('cards.printed');
        Route::get('notifications-membres', [\App\Http\Controllers\Tenant\MemberNotificationController::class, 'index'])->name('member-notifications.index');
        Route::post('notifications-membres', [\App\Http\Controllers\Tenant\MemberNotificationController::class, 'store'])->name('member-notifications.store');
        Route::get('notifications-membres/{notification}', [\App\Http\Controllers\Tenant\MemberNotificationController::class, 'show'])->name('member-notifications.show')->whereNumber('notification');
        Route::get('notifications-membres/{notification}/sms.csv', [\App\Http\Controllers\Tenant\MemberNotificationController::class, 'export'])->name('member-notifications.export')->whereNumber('notification');

        Route::get('garanties', [GuaranteeController::class, 'index'])->name('guarantees.index');
        Route::get('garanties/creer', [GuaranteeController::class, 'create'])->name('guarantees.create');
        Route::post('garanties', [GuaranteeController::class, 'store'])->name('guarantees.store');
        Route::get('garanties/{guarantee}', [GuaranteeController::class, 'show'])->name('guarantees.show');
        Route::post('garanties/{guarantee}/version', [GuaranteeController::class, 'newVersion'])->name('guarantees.version');
        Route::post('garanties/{guarantee}/reseau', [GuaranteeController::class, 'network'])->name('guarantees.network');

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
        Route::get('recours/{claim}/facture/{invoice}/piece', [ClaimSettlementController::class, 'invoiceDocument'])->name('claims.invoice.document');

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
