<?php

namespace App\Support;

/**
 * Page « Paramètres » de la mutuelle (multilingue M3a, 2026-11-30) : toutes les rubriques de
 * paramétrage, regroupées en cartes. Aucune adresse ne change : chaque carte mène à l'écran qui
 * existait déjà. Une carte n'est affichée que si la personne a le droit d'ouvrir l'écran
 * (matrice TenantAccess). Libellés en français = clés de traduction.
 */
final class TenantSettings
{
    /** Section => [route => [titre, description, icône]]. */
    public const SECTIONS = [
        'Organisation' => [
            'settings.identity'        => ['Identité et langue', 'Langue par défaut de la mutuelle et coordonnées imprimées sur les reçus et attestations.', 'globe'],
            'team.index'               => ['Équipe et rôles', 'Personnel, rôles, contrôle « 4 yeux », réinitialisation des accès.', 'lock'],
            'antennas.index'           => ['Antennes', 'Points d’accueil et zones de la mutuelle.', 'pin'],
        ],
        'Offre de soins' => [
            'formulas.index'           => ['Catalogue des formules', 'Formules, garanties, plafonds, délais d’attente et simulateur.', 'book'],
            'guarantees.index'         => ['Garanties', 'Versions datées, réseau de soins, plafonds et actes non couverts.', 'heart'],
            'formula-migrations.index' => ['Changements de formule', 'Faire passer des adhérents d’une formule à une autre, avec validation.', 'users'],
            'derogations.index'        => ['Dérogations', 'Prises en charge exceptionnelles au-delà d’un plafond.', 'shield'],
            'act-types.index'          => ['Actes et tarifs', 'Liste des actes et tarifs conventionnels.', 'stethoscope'],
            'medicines.index'          => ['Médicaments', 'Liste des médicaments, prix de référence, éligibilité.', 'pill'],
            'pharmacy.policy'          => ['Politique pharmaceutique', 'Validité des ordonnances, délivrances rapprochées, accords préalables.', 'pill'],
            'providers.index'          => ['Prestataires conventionnés', 'Conventions, demandes d’affiliation, accès des établissements.', 'stethoscope'],
        ],
        'Cotisations et adhérents' => [
            'contributions.policy'     => ['Règle de suspension', 'Délai de grâce avant la suspension des droits pour une échéance impayée.', 'coins'],
            'member-requests.index'    => ['Demandes des adhérents', 'Modifications demandées depuis le portail adhérent, à valider.', 'inbox'],
        ],
        'Réseau social' => [
            'social-presence.index'    => ['Page publique', 'Présentation de la mutuelle sur le réseau MX-CONNECT, adresse d’alerte.', 'globe'],
        ],
    ];

    /** Cartes visibles pour la personne connectée, section par section (sections vides retirées). */
    public static function visible(): array
    {
        $out = [];
        foreach (self::SECTIONS as $section => $cards) {
            $mine = array_filter($cards, fn ($v, $route) => \Illuminate\Support\Facades\Route::has($route) && TenantAccess::can($route), ARRAY_FILTER_USE_BOTH);
            if ($mine) {
                $out[$section] = $mine;
            }
        }

        return $out;
    }
}
