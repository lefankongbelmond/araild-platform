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

    /** Tout le personnel de la mutuelle (le prestataire est extérieur). */
    public const STAFF = [self::A, self::E, self::C, self::B, self::T, self::M, self::U];

    public static function rules(): array
    {
        [$A, $E, $C, $B, $T, $M, $P, $U] = [self::A, self::E, self::C, self::B, self::T, self::M, self::P, self::U];

        return [
            // --- Tout le monde (y compris le prestataire) ---
            'account.password'        => [...self::STAFF, $P],
            'account.password.update' => [...self::STAFF, $P],

            // --- Adhérents ---
            'members.index'           => [$A, $E, $C, $B, $T, $U],
            'members.show'            => [$A, $E, $C, $B, $T, $U],
            'members.create'          => [$A, $E],
            'members.store'           => [$A, $E],
            'members.update'          => [$A, $E],
            'members.suspend'         => [$A, $E],
            'members.reactivate'      => [$A, $E],
            'members.dependents.*'    => [$A, $E],
            'members.import*'         => [$A, $E],
            'members.card'            => [$A, $E, $C],
            'cards.index'             => [$A, $E, $C],
            'cards.batch'             => [$A, $E],
            'cards.printed'           => [$A, $E],
            'member-notifications.*'  => [$A, $E, $M],

            // --- Souscriptions (saisie ≠ validation) ---
            'subscriptions.index'     => [$A, $E, $C, $B, $T, $U],
            'subscriptions.show'      => [$A, $E, $C, $B, $T, $U],
            'subscriptions.create'    => [$A, $E],
            'subscriptions.store'     => [$A, $E],
            'subscriptions.validate'  => [$A, $C],

            // --- Recours (données de santé) ---
            'claims.index'                 => [$A, $C, $B, $T],
            'claims.show'                  => [$A, $C, $B, $T],
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
            'claims.invoice.document'      => [$A, $C, $B],   // pièce du prestataire : peut contenir des données de santé
            'invoices.index'               => [$A, $C, $B, $T, $U],   // registre des factures des prestataires (2026-11-03)
            'invoices.export'              => [$A, $C, $T, $U],
            'invoices.reject'              => [$A, $C],
            'reports.*'                    => [$A, $C, $B, $T, $U],   // rapports d'activité (détail par acte : capacite.donnees-sante)
            'alerts.*'                     => [$A, $C],
            // Détail médical d'un recours (motif, actes) : le trésorier ne voit que les montants.
            'capacite.donnees-sante'       => [$A, $C, $B],

            // --- Facturation, cotisations, finances ---
            'billing.*'               => [$A, $C, $B, $T],
            'contributions.index'     => [$A, $E, $C, $T, $U],
            'contributions.receipt'   => [$A, $E, $C, $T, $U],
            'contributions.pay'       => [$T],
            'payments.initiate'       => [$A, $E, $T],
            'treasury.dashboard'      => [$A, $C, $T, $U],
            'treasury.movements'      => [$A, $C, $T, $U],
            'treasury.movements.store' => [$T],
            'accounting.chart'        => [$A, $C, $T, $U],
            'accounting.journal'      => [$A, $C, $T, $U],
            'accounting.close.form'   => [$A, $C, $T, $U],
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
            'team.*'                  => [$A],
            // (garanties, antennes, actes, médicaments, référentiels : écriture = administrateur, règle par défaut)

            // --- Portail des prestataires de soins ---
            'portal.*'                => [$P],
        ];
    }

    /** Résumé lisible de la matrice (page « Rôles et droits ») : rubrique => [route => action]. */
    public const SUMMARY = [
        'Adhérents' => ['members.index' => 'Consulter', 'members.store' => 'Inscrire, modifier, suspendre', 'cards.index' => 'Cartes de membre'],
        'Souscriptions' => ['subscriptions.index' => 'Consulter', 'subscriptions.store' => 'Saisir', 'subscriptions.validate' => 'Valider'],
        'Recours et prestations (santé)' => ['claims.index' => 'Consulter', 'capacite.donnees-sante' => 'Voir le détail médical', 'claims.prestations.store' => 'Saisir', 'claims.prestations.validate' => 'Valider ou rejeter', 'alerts.index' => 'Alertes anti-fraude'],
        'Facturation et paiements' => ['billing.dashboard' => 'Facturation, quote-part', 'invoices.index' => 'Registre des factures des prestataires', 'invoices.reject' => 'Rejeter une facture', 'claims.invoice.create' => 'Préparer une facture (tiers payant)', 'claims.reimbursement.pay' => 'Payer remboursements et factures'],
        'Cotisations' => ['contributions.index' => 'Consulter', 'contributions.pay' => 'Encaisser', 'payments.initiate' => 'Lancer un paiement en ligne'],
        'Trésorerie et comptabilité' => ['treasury.dashboard' => 'Consulter', 'treasury.movements.store' => 'Saisir, comptabiliser, arrêter la caisse', 'budget.index' => 'Budget : consulter', 'budget.lines.update' => 'Budget : préparer', 'budget.adopt' => 'Budget : adopter', 'solvency.dashboard' => 'Solvabilité'],
        'Rapports' => ['reports.index' => 'Rapports d’activité (imprimer, CSV)'],
        'Conformité' => ['audit.index' => 'Journal d’audit', 'quality.index' => 'Qualité des données', 'signal.create' => 'Signaler un incident ou un risque', 'risks.index' => 'Registres risques et incidents', 'risks.status' => 'Traiter risques et incidents', 'complaints.index' => 'Réclamations : consulter', 'complaints.store' => 'Réclamations : enregistrer', 'complaints.status' => 'Réclamations : décider'],
        'Réseau social' => ['social-presence.index' => 'Page, publications, groupes, projets', 'engagement.requests.cv' => 'Candidatures (CV)', 'online-adhesions.index' => 'Adhésions en ligne', 'conversations.index' => 'Messagerie'],
        'Paramètres' => ['guarantees.index' => 'Garanties, actes, médicaments : consulter', 'guarantees.store' => 'Garanties, actes, médicaments : modifier', 'providers.index' => 'Prestataires conventionnés : consulter', 'providers.store' => 'Prestataires conventionnés : gérer', 'team.index' => 'Équipe et rôles'],
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

        return $user instanceof User && self::allows($route, $user->getRoleNames()->all());
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
                if (\Route::has($route) && self::can($route, $user)) {
                    return route($route);
                }
            }
        }

        return route('account.password');
    }
}
