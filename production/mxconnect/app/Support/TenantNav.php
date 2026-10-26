<?php

namespace App\Support;

/**
 * Navigation du back-office des mutuelles ET règles d'accès au menu, en un seul
 * endroit : utilisé par le menu (layout « tenant ») et par la page « Rôles et droits »,
 * qui affiche donc exactement ce que le code applique.
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
    ];

    public const ROLE_DESCRIPTIONS = [
        'mutual_admin'         => 'Pilote la mutuelle : tous les écrans, l’équipe et les rôles, les décisions finales.',
        'enrollment_agent'     => 'Saisit les adhésions et les souscriptions ; instruit les adhésions en ligne.',
        'controller_validator' => 'Valide ce que d’autres ont saisi (adhésions, prestations) : contrôle « 4 yeux ».',
        'benefits_manager'     => 'Saisit les prestations et les prises en charge.',
        'treasurer_accountant' => 'Trésorerie, cotisations, comptabilité SYSCOHADA, budget.',
        'community_manager'    => 'Page publique, publications, groupes et messages du réseau social.',
        'provider'             => 'Prestataire de soins conventionné (accès limité).',
    ];

    /** Rubriques réservées à certains rôles (les autres sont visibles par toute l'équipe). */
    public static function roles(): array
    {
        return [
            'social-presence.index'      => ['mutual_admin', 'community_manager'],
            'online-adhesions.index'     => ['mutual_admin', 'enrollment_agent', 'controller_validator'],
            'engagement.index'           => \App\Http\Controllers\Tenant\EngagementController::ROLES,
            'conversations.index'        => ['mutual_admin', 'community_manager', 'enrollment_agent'],
            'cards.index'                => \App\Http\Controllers\Tenant\MemberCardController::ROLES,
            'member-notifications.index' => \App\Http\Controllers\Tenant\MemberNotificationController::ROLES,
            'billing.dashboard'          => \App\Http\Controllers\Tenant\BillingController::ROLES,
            'billing.quote'              => \App\Http\Controllers\Tenant\BillingController::QUOTE_ROLES,
            'budget.index'               => \App\Http\Controllers\Tenant\BudgetController::ROLES,
            'team.index'                 => \App\Http\Controllers\Tenant\TeamController::ROLES,
        ];
    }

    /** Menu regroupé par domaine : route => [libellé ou clé de traduction, icône]. */
    public static function groups(): array
    {
        return [
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
                'contributions.index' => ['mxconnect.contribution.plural', 'coins'],
                'budget.index'        => ['Budget', 'pie'],
                'treasury.dashboard'  => ['mxconnect.treasury.title', 'wallet'],
                'accounting.journal'  => ['mxconnect.accounting.journal', 'book'],
                'solvency.dashboard'  => ['mxconnect.solvency.nav', 'chart'],
            ],
            'Conformité et qualité' => [
                'audit.index'         => ['mxconnect.audit.title', 'history'],
                'quality.index'       => ['mxconnect.quality.nav', 'shield'],
                'risks.index'         => ['mxconnect.risk.nav', 'alert'],
                'incidents.index'     => ['mxconnect.incident.nav', 'zap'],
                'complaints.index'    => ['mxconnect.complaint.nav', 'inbox'],
            ],
            'Réseau social' => [
                'social-presence.index'  => ['mxconnect.social.nav', 'globe'],
                'online-adhesions.index' => ['mxconnect.social.adhesions_nav', 'user-plus'],
                'engagement.index'       => ['mxconnect.social.engagement_nav', 'clipboard'],
                'conversations.index'    => ['mxconnect.social.messages_nav', 'message'],
            ],
            'Paramètres' => [
                'team.index'       => ['Équipe et rôles', 'lock'],
                'guarantees.index' => ['mxconnect.guarantee.plural', 'heart'],
                'antennas.index'   => ['mxconnect.param.antennas', 'pin'],
                'act-types.index'  => ['mxconnect.param.act_types', 'stethoscope'],
                'medicines.index'  => ['mxconnect.param.medicines', 'pill'],
            ],
        ];
    }

    /** Une rubrique est-elle accessible à ce rôle (selon les règles du menu) ? */
    public static function allows(string $route, string $role): bool
    {
        $rules = self::roles();

        return ! isset($rules[$route]) || in_array($role, $rules[$route], true);
    }
}
