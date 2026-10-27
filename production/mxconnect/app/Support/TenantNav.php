<?php

namespace App\Support;

/**
 * Navigation du back-office des mutuelles. Les droits viennent de la matrice
 * App\Support\TenantAccess (appliquée aussi à chaque route) : le menu et la page
 * « Rôles et droits » affichent donc exactement ce que le code applique.
 */
class TenantNav
{
    public const ROLE_LABELS = [
        'mutual_admin'         => 'Administrateur',
        'enrollment_agent'     => 'Agent d’adhésion',
        'controller_validator' => 'Contrôleur-validateur',
        'benefits_manager'     => 'Gestionnaire des prestations',
        'treasurer_accountant' => 'Trésorier-comptable',
        'community_manager'    => 'Chargé de communication',
        'provider'             => 'Prestataire de soins',
        'auditor'              => 'Auditeur / contrôle interne',
    ];

    public const ROLE_DESCRIPTIONS = [
        'mutual_admin'         => 'Pilote la mutuelle : tous les écrans, l’équipe et les rôles, les décisions finales.',
        'enrollment_agent'     => 'Saisit les adhésions et les souscriptions ; instruit les adhésions en ligne.',
        'controller_validator' => 'Valide ce que d’autres ont saisi (adhésions, prestations) : contrôle « 4 yeux ».',
        'benefits_manager'     => 'Saisit les recours et les prestations (données de santé) ; prépare la facturation.',
        'treasurer_accountant' => 'Cotisations, paiements, trésorerie, comptabilité SYSCOHADA, budget. Voit les montants des recours, pas leur détail médical.',
        'community_manager'    => 'Page publique, publications, groupes, projets et messages du réseau social.',
        'provider'             => 'Prestataire de soins conventionné : uniquement son portail (vérifier les droits, déclarer ses prises en charge, suivre ses factures).',
        'auditor'              => 'Lecture seule : finances, registres, journal d’audit. Aucune donnée de santé, aucune saisie. Ne se cumule avec aucun autre rôle.',
    ];

    /** Écrans sans entrée propre dans le menu : entrée à mettre en évidence. */
    public const ACTIVE_ALIASES = [
        'accounting.ledger' => 'accounting.balance',
        'accounting.income' => 'accounting.balance',
        'accounting.sheet'  => 'accounting.balance',
    ];

    /** Menu regroupé par domaine : route => [libellé ou clé de traduction, icône]. */
    public static function groups(): array
    {
        return [
            'Portail prestataire' => [
                'portal.index'    => ['Vérifier les droits', 'shield'],
                'portal.claims'   => ['Mes prises en charge', 'clipboard'],
                'portal.invoices' => ['Mes factures', 'receipt'],
            ],
            'Gestion des adhérents' => [
                'members.index'       => ['mxconnect.member.plural', 'users'],
                'cards.index'         => ['Cartes de membre', 'card'],
                'member-notifications.index' => ['Notifications aux membres', 'send'],
                'subscriptions.index' => ['mxconnect.subscription.plural', 'file'],
                'claims.index'        => ['mxconnect.claim.plural', 'clipboard'],
                'alerts.index'        => ['mxconnect.alert.plural', 'bell'],
            ],
            'Facturation et finances' => [
                'billing.dashboard'   => ['Facturation', 'receipt'],
                'billing.quote'       => ['Calcul de quote-part', 'calculator'],
                'invoices.index'      => ['Factures des prestataires', 'receipt'],
                'reports.index'       => ['Rapports d’activité', 'chart'],
                'contributions.index' => ['mxconnect.contribution.plural', 'coins'],
                'budget.index'        => ['Budget', 'pie'],
                'treasury.dashboard'  => ['mxconnect.treasury.title', 'wallet'],
                'accounting.journal'  => ['mxconnect.accounting.journal', 'book'],
                'accounting.balance'  => ['États comptables', 'book'],
                'solvency.dashboard'  => ['mxconnect.solvency.nav', 'chart'],
            ],
            'Conformité et qualité' => [
                'audit.index'         => ['mxconnect.audit.title', 'history'],
                'quality.index'       => ['mxconnect.quality.nav', 'shield'],
                'risks.index'         => ['mxconnect.risk.nav', 'alert'],
                'incidents.index'     => ['mxconnect.incident.nav', 'zap'],
                'complaints.index'    => ['mxconnect.complaint.nav', 'inbox'],
                'signal.create'       => ['Signaler un incident', 'alert'],
            ],
            'Réseau social' => [
                'social-presence.index'  => ['mxconnect.social.nav', 'globe'],
                'online-adhesions.index' => ['mxconnect.social.adhesions_nav', 'user-plus'],
                'engagement.index'       => ['mxconnect.social.engagement_nav', 'clipboard'],
                'conversations.index'    => ['mxconnect.social.messages_nav', 'message'],
            ],
            'Paramètres' => [
                'team.index'       => ['Équipe et rôles', 'lock'],
                'providers.index'  => ['Prestataires conventionnés', 'stethoscope'],
                'guarantees.index' => ['mxconnect.guarantee.plural', 'heart'],
                'antennas.index'   => ['mxconnect.param.antennas', 'pin'],
                'act-types.index'  => ['mxconnect.param.act_types', 'stethoscope'],
                'medicines.index'  => ['mxconnect.param.medicines', 'pill'],
            ],
        ];
    }

    /** Une rubrique est-elle accessible à ce rôle ? (matrice TenantAccess) */
    public static function allows(string $route, string $role): bool
    {
        return TenantAccess::allows($route, $role);
    }
}
