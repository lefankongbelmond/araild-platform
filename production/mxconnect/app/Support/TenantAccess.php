<?php

namespace App\Support;

use App\Models\Tenant\User;

/**
 * Matrice des droits du back-office des mutuelles (validée par le fondateur le 2026-10-31).
 *
 * SOURCE UNIQUE : appliquée à CHAQUE route par le middleware TenantRouteAccess (une URL
 * saisie à la main est refusée, pas seulement masquée du menu), relue par le menu
 * (TenantNav), par les boutons des écrans et par la page « Rôles et droits ».
 *
 * Clé = nom de route (ou préfixe « x.* ») ; valeur = rôles autorisés.
 * Une route absente de la matrice est réservée à l'administrateur (refus par défaut).
 * Les clés « capacite.* » ne sont pas des routes : ce sont des droits d'affichage.
 */
class TenantAccess
{
    public const A = 'mutual_admin';
    public const E = 'enrollment_agent';
    public const C = 'controller_validator';
    public const B = 'benefits_manager';
    public const T = 'treasurer_accountant';
    public const M = 'community_manager';
    public const P = 'provider';
    public const U = 'auditor';
    public const D = 'medical_advisor';   // médecin-conseil (2026-11-19)
    public const K = 'contribution_manager';   // gestionnaire des cotisations (2026-11-22)

    /** Tout le personnel de la mutuelle (le prestataire est extérieur). */
    public const STAFF = [self::A, self::E, self::C, self::B, self::T, self::M, self::U, self::D, self::K];

    public static function rules(): array
    {
        [$A, $E, $C, $B, $T, $M, $P, $U, $D, $K] = [self::A, self::E, self::C, self::B, self::T, self::M, self::P, self::U, self::D, self::K];

        return [
            // --- Tout le monde (y compris le prestataire) ---
            'account.password'        => [...self::STAFF, $P],
            'account.password.update' => [...self::STAFF, $P],

            // --- Adhérents ---
            'members.index'           => [$A, $E, $C, $B, $T, $U, $K],
            'members.show'            => [$A, $E, $C, $B, $T, $U, $K],
            'members.create'          => [$A, $E],
            'members.store'           => [$A, $E],
            'members.update'          => [$A, $E],
            'members.suspend'         => [$A, $E],
            'members.reactivate'      => [$A, $E],
            'members.dependents.*'    => [$A, $E],
            'members.import*'         => [$A, $E],
            'members.card'            => [$A, $E, $C],
            'members.portal.*'        => [$A, $E],   // portail adhérent : code d'activation, retrait (2026-11-20)
            'member-requests.*'       => [$A, $E],   // demandes de modification faites depuis le portail
            'test-env.*'              => [$A],       // MUTUELLE TEST : réinitialisation des données d'essai (2026-11-22)
            'cards.index'             => [$A, $E, $C],
            'cards.batch'             => [$A, $E],
            'cards.printed'           => [$A, $E],
            'member-notifications.*'  => [$A, $E, $M],

            // --- Souscriptions (saisie ≠ validation) ---
            'subscriptions.index'     => [$A, $E, $C, $B, $T, $U, $K],
            'subscriptions.show'      => [$A, $E, $C, $B, $T, $U, $K],
            'subscriptions.create'    => [$A, $E],
            'subscriptions.store'     => [$A, $E],
            'subscriptions.validate'  => [$A, $C],

            // --- Recours (données de santé) ---
            'claims.index'                 => [$A, $C, $B, $T, $D],
            'claims.show'                  => [$A, $C, $B, $T, $D],
            'claims.create'                => [$B],
            'claims.store'                 => [$B],
            'claims.prestations.store'     => [$B],
            'claims.prestations.validate'  => [$A, $C],
            'claims.prestations.reject'    => [$A, $C],
            'claims.close'                 => [$A, $C],
            'claims.reimbursement.request' => [$A, $C, $T],
            'claims.reimbursement.pay'     => [$T],
            'claims.invoice.create'        => [$A, $C, $B],
            'claims.invoice.pay'           => [$T],
            'claims.invoice.document'      => [$A, $C, $B, $D],   // pièce du prestataire : peut contenir des données de santé
            'invoices.index'               => [$A, $C, $B, $T, $U],   // registre des factures des prestataires (2026-11-03)
            'invoices.export'              => [$A, $C, $T, $U],
            'invoices.reject'              => [$A, $C],
            'reports.*'                    => [$A, $C, $B, $T, $U],   // rapports d'activité (détail par acte : capacite.donnees-sante)
            'alerts.*'                     => [$A, $C],
            // Détail médical d'un recours (motif, actes) : le trésorier ne voit que les montants.
            'capacite.donnees-sante'       => [$A, $C, $B, $D],

            // --- Facturation, cotisations, finances ---
            'billing.*'               => [$A, $C, $B, $T],
            'contributions.index'     => [$A, $E, $C, $T, $U, $K],
            'contributions.receipt'   => [$A, $E, $C, $T, $U, $K],
            'contributions.pay'       => [$T, $K],
            'contributions.member'    => [$A, $E, $C, $T, $U, $K],   // relevé d'un adhérent (2026-11-06)
            'contributions.export'    => [$A, $E, $C, $T, $U, $K],   // CSV pour les relances
            'contributions.collect'   => [$T, $K],                   // encaissement au guichet
            'contributions.refresh'   => [$A, $T, $K],               // échéanciers glissants
            'contributions.cancel.request' => [$T, $K],              // annulation d'un encaissement : demande (2026-11-07)
            'contributions.cancel.approve' => [$A, $C],          // … décision par une AUTRE personne
            'contributions.cancel.reject'  => [$A, $C],
            'contributions.fee'       => [$A, $T, $K],               // appeler le droit d'adhésion
            'payments.initiate'       => [$A, $E, $T],
            'treasury.dashboard'      => [$A, $C, $T, $U],
            'treasury.movements'      => [$A, $C, $T, $U],
            'treasury.movements.store' => [$T],
            'accounting.chart'        => [$A, $C, $T, $U],
            'accounting.journal'      => [$A, $C, $T, $U],
            'accounting.close.form'   => [$A, $C, $T, $U],
            'accounting.balance'      => [$A, $C, $T, $U],   // états comptables SYSCOHADA (2026-11-04)
            'accounting.ledger'       => [$A, $C, $T, $U],
            'accounting.income'       => [$A, $C, $T, $U],
            'accounting.sheet'        => [$A, $C, $T, $U],
            'accounting.statements.export' => [$A, $C, $T, $U],
            'accounting.entries.index'    => [$A, $C, $T, $U],   // opérations diverses (2026-11-05)
            'accounting.entries.store'    => [$T],               // saisie : trésorier-comptable
            'accounting.entries.validate' => [$A, $C],           // validation par une AUTRE personne
            'accounting.entries.reject'   => [$A, $C],
            'accounting.entries.reverse'  => [$T],               // extourne préparée, puis validée par A/C
            'accounting.accounts.*'       => [$A, $T],           // plan comptable
            'accounting.closing.index'    => [$A, $C, $T, $U],   // clôture d'exercice
            'accounting.closing.close'    => [$A, $T],
            'accounting.closing.reopen'   => [$A],
            'accounting.post-treasury' => [$T],
            'accounting.close'        => [$T],
            'budget.index'            => [$A, $C, $T, $U],
            'budget.create'           => [$T],
            'budget.lines.*'          => [$T],
            'budget.adopt'            => [$A],
            'solvency.dashboard'      => [$A, $C, $T, $U],

            // --- Conformité ---
            'audit.index'             => [$A, $C, $U],
            'quality.index'           => [$A, $E, $C, $U],
            'signal.*'                => self::STAFF,      // « Signaler un incident ou un risque »
            'risks.store'             => self::STAFF,
            'incidents.store'         => self::STAFF,
            'risks.index'             => [$A, $C, $U],
            'risks.show'              => [$A, $C, $U],
            'incidents.index'         => [$A, $C, $U],
            'incidents.show'          => [$A, $C, $U],
            'risks.status'            => [$A, $C],
            'incidents.status'        => [$A, $C],
            'complaints.index'        => [$A, $E, $C, $B, $M],
            'complaints.show'         => [$A, $E, $C, $B, $M],
            'complaints.store'        => [$A, $E, $C, $M],
            'complaints.status'       => [$A, $C],
            'complaints.reply'        => [$A, $E, $C, $M],

            // --- Réseau social ---
            'social-presence.*'       => [$A, $M],
            'social-groups.*'         => [$A, $M],
            'engagement.*'            => [$A, $M],
            'engagement.requests.cv'  => [$A],             // candidatures : administrateur seul
            'online-adhesions.*'      => [$A, $E, $C],
            'conversations.*'         => [$A, $E, $M],

            // --- Paramètres ---
            'guarantees.index'        => [$A, $E, $C, $B, $T],
            'guarantees.show'         => [$A, $E, $C, $B, $T],
            'antennas.index'          => [$A, $E, $C, $B, $T],
            'act-types.index'         => [$A, $E, $C, $B, $T],
            'medicines.index'         => [$A, $E, $C, $B, $T],
            'providers.index'         => [$A, $C, $B],
            'providers.show'          => [$A, $C, $B],
            'providers.*'             => [$A],
            'guarantees.network'      => [$A],            // réseau de soins d'une garantie (2026-11-01)
            'guarantees.terms'        => [$A],            // plafonds et exclusions (2026-11-03)
            // Catalogue des formules (2026-11-18) : lecture et simulation pour le personnel qui en a
            // besoin ; toute modification = administrateur (règle « formulas.* »).
            'formulas.index'          => [$A, $E, $C, $B, $T],
            'formulas.show'           => [$A, $E, $C, $B, $T],
            'formulas.categories'     => [$A, $E, $C, $B, $T],
            'formulas.simulate'       => [$A, $E, $C, $B, $T],
            'formula-migrations.index'   => [$A, $E, $C, $U],   // changements de formule (2026-11-23) : consulter
            'formula-migrations.show'    => [$A, $E, $C, $U],
            'formula-migrations.create'  => [$A],               // demander (aperçu des adhérents)
            'formula-migrations.store'   => [$A],
            'formula-migrations.cancel'  => [$A],
            'formula-migrations.approve' => [$A, $C],           // décider : jamais la personne qui a demandé (contrôlé dans le service)
            'formula-migrations.reject'  => [$A, $C],
            'formula-migrations.apply'   => [$A, $C],           // appliquer à la date d'effet (sinon tâche de nuit)
            'formulas.*'              => [$A],
            // Dérogations : demande par le gestionnaire des prestations, décision par le contrôleur
            // (jamais par la personne qui a demandé : contrôlé dans le contrôleur).
            'derogations.index'       => [$A, $C, $B, $D],
            'derogations.store'       => [$A, $B],
            'derogations.approve'     => [$A, $C, $D],
            'derogations.reject'      => [$A, $C, $D],
            'derogations.revoke'      => [$A, $C, $D],
            // Accords préalables (2026-11-19) : demande par le gestionnaire des prestations, décision
            // par une autre personne ; examen médical = médecin-conseil seulement (contrôlé dans le service).
            'preauth.index'           => [$A, $C, $B, $D],
            'preauth.show'            => [$A, $C, $B, $D],
            'preauth.store'           => [$A, $B],
            'preauth.decide'          => [$A, $C, $D],
            // Pharmacie (2026-11-19) : registre des délivrances, politique pharmaceutique.
            'pharmacy.index'          => [$A, $C, $B, $D],
            'pharmacy.*'              => [$A],
            'team.*'                  => [$A],
            // (garanties, antennes, actes, médicaments, référentiels : écriture = administrateur, règle par défaut)

            // --- Portail des prestataires de soins ---
            'portal.*'                => [$P],
        ];
    }

    /** Résumé lisible de la matrice (page « Rôles et droits ») : rubrique => [route => action]. */
    public const SUMMARY = [
        'Adhérents' => ['member-requests.index' => 'Demandes des adhérents (portail)', 'members.portal.code' => 'Codes d’activation du portail adhérent', 'members.index' => 'Consulter', 'members.store' => 'Inscrire, modifier, suspendre', 'cards.index' => 'Cartes de membre'],
        'Souscriptions' => ['subscriptions.index' => 'Consulter', 'subscriptions.store' => 'Saisir', 'subscriptions.validate' => 'Valider'],
        'Recours et prestations (santé)' => ['claims.index' => 'Consulter', 'capacite.donnees-sante' => 'Voir le détail médical', 'claims.prestations.store' => 'Saisir', 'claims.prestations.validate' => 'Valider ou rejeter', 'alerts.index' => 'Alertes anti-fraude'],
        'Facturation et paiements' => ['billing.dashboard' => 'Facturation, quote-part', 'invoices.index' => 'Registre des factures des prestataires', 'invoices.reject' => 'Rejeter une facture', 'claims.invoice.create' => 'Préparer une facture (tiers payant)', 'claims.reimbursement.pay' => 'Payer remboursements et factures'],
        'Cotisations' => ['contributions.index' => 'Consulter, relevé par adhérent, export CSV', 'contributions.collect' => 'Encaisser (partiel ou plusieurs échéances)', 'contributions.refresh' => 'Actualiser les échéanciers', 'contributions.cancel.request' => 'Demander l’annulation d’un encaissement', 'contributions.cancel.approve' => 'Valider ou refuser une annulation', 'contributions.fee' => 'Appeler le droit d’adhésion', 'payments.initiate' => 'Lancer un paiement en ligne'],
        'Trésorerie et comptabilité' => ['treasury.dashboard' => 'Consulter', 'treasury.movements.store' => 'Saisir, comptabiliser, arrêter la caisse',
            'accounting.entries.store' => 'Opérations diverses : saisir, préparer une extourne', 'accounting.entries.validate' => 'Opérations diverses : valider ou rejeter',
            'accounting.accounts.store' => 'Plan comptable : ajouter, renommer', 'accounting.closing.close' => 'Clôturer un exercice', 'accounting.closing.reopen' => 'Rouvrir un exercice', 'budget.index' => 'Budget : consulter', 'budget.lines.update' => 'Budget : préparer', 'budget.adopt' => 'Budget : adopter', 'solvency.dashboard' => 'Solvabilité'],
        'Rapports' => ['reports.dashboard' => 'Tableau de bord de pilotage (filtres, Excel, impression)', 'reports.index' => 'Rapports d’activité (imprimer, CSV)', 'accounting.balance' => 'États comptables : balance, grand livre, compte de résultat, bilan'],
        'Conformité' => ['audit.index' => 'Journal d’audit', 'quality.index' => 'Qualité des données', 'signal.create' => 'Signaler un incident ou un risque', 'risks.index' => 'Registres risques et incidents', 'risks.status' => 'Traiter risques et incidents', 'complaints.index' => 'Réclamations : consulter', 'complaints.store' => 'Réclamations : enregistrer', 'complaints.status' => 'Réclamations : décider'],
        'Réseau social' => ['social-presence.index' => 'Page, publications, groupes, projets', 'engagement.requests.cv' => 'Candidatures (CV)', 'online-adhesions.index' => 'Adhésions en ligne', 'conversations.index' => 'Messagerie'],
        'Accords préalables et pharmacie' => ['preauth.index' => 'Accords préalables : consulter', 'preauth.store' => 'Accords préalables : demander', 'preauth.decide' => 'Accords préalables : décider (examen médical : médecin-conseil)', 'pharmacy.index' => 'Registre des délivrances en pharmacie', 'pharmacy.policy' => 'Politique pharmaceutique et liste des médicaments'],
        'Formules et garanties' => ['formulas.index' => 'Catalogue des formules : consulter, simuler', 'formulas.rules.store' => 'Catalogue des formules : modifier (garanties, plafonds, carences)', 'derogations.index' => 'Dérogations : consulter', 'derogations.store' => 'Dérogations : demander', 'derogations.approve' => 'Dérogations : accorder ou refuser', 'formula-migrations.index' => 'Changements de formule : consulter', 'formula-migrations.store' => 'Changements de formule : demander', 'formula-migrations.approve' => 'Changements de formule : valider ou refuser, appliquer'],
        'Paramètres' => ['guarantees.index' => 'Garanties, actes, médicaments : consulter', 'guarantees.store' => 'Garanties, actes, médicaments : modifier', 'providers.index' => 'Prestataires conventionnés : consulter', 'providers.store' => 'Prestataires conventionnés : gérer', 'team.index' => 'Équipe et rôles', 'test-env.index' => 'MUTUELLE TEST : réinitialiser les données d’essai'],
        'Portail prestataire' => ['portal.index' => 'Vérifier les droits, déclarer, suivre ses factures'],
    ];

    /** Clé de la matrice qui s'applique à une route : nom exact, sinon le préfixe « x.* » / « x* » le plus long. */
    public static function ruleFor(string $route): ?string
    {
        $rules = self::rules();
        if (isset($rules[$route])) {
            return $route;
        }
        $best = null;
        foreach (array_keys($rules) as $pattern) {
            if (str_ends_with($pattern, '*') && str_starts_with($route, rtrim($pattern, '*'))
                && ($best === null || strlen($pattern) > strlen($best))) {
                $best = $pattern;
            }
        }

        return $best;
    }

    /** Rôles autorisés pour une route ; sans règle : administrateur seul. */
    public static function rolesFor(string $route): array
    {
        $key = self::ruleFor($route);

        return $key !== null ? self::rules()[$key] : [self::A];
    }

    /** @param iterable<string>|string $roles */
    public static function allows(string $route, iterable|string $roles): bool
    {
        $allowed = self::rolesFor($route);
        foreach ((array) (is_string($roles) ? [$roles] : $roles) as $role) {
            if (in_array($role, $allowed, true)) {
                return true;
            }
        }

        return false;
    }

    /** L'utilisateur connecté (ou donné) a-t-il accès à cette route / capacité ? */
    public static function can(string $route, ?User $user = null): bool
    {
        $user ??= auth('web')->user();
        if (! $user instanceof User || ! self::allows($route, $user->getRoleNames()->all())) {
            return false;
        }
        // Compte ouvert depuis l'espace prestataire central (2026-11-16) : en plus du rôle
        // « Prestataire de soins », le droit de la personne DANS son établissement est exigé.
        $perm = \App\Services\Provider\ProviderSpaceService::PORTAL_ROUTE_PERMS[$route] ?? null;
        if ($perm !== null && ($user->provider_account_id ?? null)) {
            return in_array($perm, self::linkedPermissions($user), true);
        }

        return true;
    }

    /** Droits d'établissement d'un compte rattaché (lus une fois par requête). */
    public static function linkedPermissions(User $user): array
    {
        $key = 'provider_perms_' . $user->id;
        $attrs = request()->attributes;
        if (! $attrs->has($key)) {
            $attrs->set($key, app(\App\Services\Provider\ProviderSpaceService::class)->linkedPermissions($user) ?? []);
        }

        return $attrs->get($key);
    }

    /** Refuse (403) si l'utilisateur connecté n'a pas accès à la route courante (ou donnée). */
    public static function authorize(?string $route = null): void
    {
        abort_unless(self::can($route ?? (string) request()->route()?->getName()), 403,
            'Cette action n’est pas ouverte à votre rôle.');
    }

    /** Écran d'accueil après connexion : la 1re rubrique du menu accessible. */
    public static function home(User $user): string
    {
        foreach (TenantNav::groups() as $items) {
            foreach (array_keys($items) as $route) {
                // L5 : le tableau de bord de pilotage (en tête du menu) ne change pas la page d'arrivée.
                if ($route === 'reports.dashboard') {
                    continue;
                }
                if (\Route::has($route) && self::can($route, $user)) {
                    return route($route);
                }
            }
        }

        return route('account.password');
    }
}
