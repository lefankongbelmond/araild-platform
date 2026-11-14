<?php

namespace App\Support;

use App\Models\Provider\ProviderMembership;

/**
 * Droits DANS un établissement de soins (espace prestataire central, 2026-11-16).
 * Source unique : menu, routes (middleware ProviderSpaceGuard via ->defaults('perm')), vues
 * (@droit), portail des mutuelles (ProviderPortalController) et page « Personnel ».
 *
 * Chaque rôle reçoit des droits par défaut ; l'administrateur de l'établissement peut en
 * AJOUTER ou en RETIRER personne par personne (provider_memberships.permissions), sauf
 * « staff.manage » qui reste lié au rôle Administrateur (l'établissement garde toujours au
 * moins un administrateur actif).
 */
final class ProviderAccess
{
    public const ROLES = [
        'admin'      => 'Administrateur de l’établissement',
        'doctor'     => 'Médecin',
        'nurse'      => 'Infirmier / infirmière',
        'midwife'    => 'Sage-femme',
        'pharmacist' => 'Pharmacien',
        'lab_tech'   => 'Technicien de laboratoire',
        'cashier'    => 'Caissier / accueil',
        'billing'    => 'Agent de facturation',
        'manager'    => 'Gestionnaire administratif',
    ];

    /** Droits, par groupe (libellés affichés dans « Personnel »). */
    public const PERMISSIONS = [
        'Parcours de soins' => [
            'rights.verify' => 'Vérifier les droits d’un adhérent',
            'care.declare'  => 'Déclarer une prise en charge (soins réalisés)',
            'claims.view'   => 'Consulter les prises en charge de l’établissement',
        ],
        'Facturation' => [
            'invoices.view' => 'Consulter les factures',
            'invoices.bill' => 'Demander le paiement (facture tiers payant)',
            'finance.view'  => 'Voir les montants du tableau de bord',
        ],
        'Établissement' => [
            'conventions.view'    => 'Consulter les conventions et les tarifs',
            'affiliation.request' => 'Demander une affiliation à une mutuelle',
            'profile.edit'        => 'Modifier la fiche de l’établissement',
            'bank.edit'           => 'Modifier les coordonnées bancaires',
            'documents.manage'    => 'Gérer les agréments et documents',
            'staff.manage'        => 'Gérer le personnel et les droits',
        ],
    ];

    private const DEFAULTS = [
        'admin'      => ['rights.verify', 'care.declare', 'claims.view', 'invoices.view', 'invoices.bill', 'finance.view', 'conventions.view', 'affiliation.request', 'profile.edit', 'bank.edit', 'documents.manage', 'staff.manage'],
        'doctor'     => ['rights.verify', 'care.declare', 'claims.view', 'conventions.view'],
        'nurse'      => ['rights.verify', 'care.declare', 'claims.view', 'conventions.view'],
        'midwife'    => ['rights.verify', 'care.declare', 'claims.view', 'conventions.view'],
        'pharmacist' => ['rights.verify', 'care.declare', 'claims.view', 'conventions.view'],
        'lab_tech'   => ['rights.verify', 'care.declare', 'claims.view', 'conventions.view'],
        'cashier'    => ['rights.verify', 'claims.view', 'invoices.view', 'finance.view', 'conventions.view'],
        'billing'    => ['claims.view', 'invoices.view', 'invoices.bill', 'finance.view', 'conventions.view'],
        'manager'    => ['claims.view', 'invoices.view', 'finance.view', 'conventions.view', 'affiliation.request', 'profile.edit', 'documents.manage'],
    ];

    /** Droit exigé par chaque route de l'espace prestataire (null = tout le personnel). */
    public const ROUTES = [
        'pro.dashboard'            => null,
        'pro.switch'               => null,
        'pro.go'                   => null,              // le droit dépend de la destination (service)
        'pro.open'                 => null,
        'pro.mutuals'              => 'conventions.view',
        'pro.mutuals.show'         => 'conventions.view',
        'pro.affiliations.store'   => 'affiliation.request',
        'pro.affiliations.withdraw'=> 'affiliation.request',
        'pro.profile'              => null,
        'pro.profile.update'       => 'profile.edit',
        'pro.bank.update'          => 'bank.edit',
        'pro.staff'                => 'staff.manage',
        'pro.staff.store'          => 'staff.manage',
        'pro.staff.update'         => 'staff.manage',
        'pro.staff.reset'          => 'staff.manage',
        'pro.documents'            => 'conventions.view',
        'pro.documents.download'   => 'conventions.view',
        'pro.documents.store'      => 'documents.manage',
        'pro.documents.destroy'    => 'documents.manage',
        'pro.settings'             => null,
        'pro.settings.update'      => null,
        'pro.settings.password'    => null,
    ];

    /** Droit réservé au rôle Administrateur (jamais ajouté ni retiré individuellement). */
    public const ROLE_BOUND = ['staff.manage'];

    public static function all(): array
    {
        return array_merge(...array_values(array_map('array_keys', self::PERMISSIONS)));
    }

    public static function label(string $perm): string
    {
        foreach (self::PERMISSIONS as $items) {
            if (isset($items[$perm])) {
                return $items[$perm];
            }
        }

        return $perm;
    }

    public static function defaults(string $role): array
    {
        return self::DEFAULTS[$role] ?? [];
    }

    /** @param array{grant?:array,deny?:array} $overrides */
    public static function effective(string $role, array $overrides): array
    {
        $grant = array_diff(array_intersect((array) ($overrides['grant'] ?? []), self::all()), self::ROLE_BOUND);
        $deny = array_diff(array_intersect((array) ($overrides['deny'] ?? []), self::all()), self::ROLE_BOUND);

        return array_values(array_diff(array_unique(array_merge(self::defaults($role), $grant)), $deny));
    }

    /** Écart entre les droits cochés et ceux du rôle => {grant, deny} à enregistrer. */
    public static function overridesFor(string $role, array $checked): array
    {
        $checked = array_diff(array_intersect($checked, self::all()), self::ROLE_BOUND);
        $base = array_diff(self::defaults($role), self::ROLE_BOUND);

        return ['grant' => array_values(array_diff($checked, $base)), 'deny' => array_values(array_diff($base, $checked))];
    }

    /** Rattachement en cours (posé par le middleware de l'espace prestataire). */
    public static function current(): ?ProviderMembership
    {
        return app()->bound('provider.membership') ? app('provider.membership') : null;
    }

    public static function allowed(string $perm, ?ProviderMembership $m = null): bool
    {
        $m ??= self::current();

        return $m !== null && $m->can($perm);
    }

    /** Route de l'espace prestataire ouverte au rattachement en cours ? (route non classée = refus) */
    public static function canRoute(string $route, ?ProviderMembership $m = null): bool
    {
        if (! array_key_exists($route, self::ROUTES)) {
            return false;
        }
        $perm = self::ROUTES[$route];

        return $perm === null ? (($m ?? self::current()) !== null) : self::allowed($perm, $m);
    }
}
