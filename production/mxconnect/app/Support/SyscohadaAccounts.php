<?php

namespace App\Support;

/**
 * Comptes SYSCOHADA usuels d'une mutuelle de santé (2026-11-05), proposés à l'import dans
 * le plan comptable. Liste de départ, sans montant : chaque mutuelle l'adapte avec son
 * expert-comptable (ajout, renommage, suppression d'un compte inutilisé).
 */
class SyscohadaAccounts
{
    /** numéro => intitulé (la classe est le premier chiffre). */
    public const USUAL = [
        '101'  => 'Fonds propres de la mutuelle (fonds d’établissement)',
        '111'  => 'Réserves statutaires',
        '118'  => 'Autres réserves (fonds de garantie)',
        '121'  => 'Report à nouveau créditeur',
        '129'  => 'Report à nouveau débiteur',
        '130'  => 'Résultat en instance d’affectation',
        '131'  => 'Résultat net : bénéfice',
        '139'  => 'Résultat net : perte',
        '141'  => 'Subventions d’équipement reçues',
        '2441' => 'Matériel de bureau',
        '2444' => 'Mobilier de bureau',
        '2445' => 'Matériel informatique',
        '2451' => 'Matériel de transport',
        '2844' => 'Amortissements du matériel et mobilier',
        '401'  => 'Fournisseurs',
        '4011' => 'Prestataires de soins : factures à payer',
        '411'  => 'Adhérents : cotisations à recevoir',
        '419'  => 'Adhérents : cotisations reçues d’avance',
        '421'  => 'Personnel : rémunérations dues',
        '431'  => 'Sécurité sociale (cotisations sociales)',
        '447'  => 'État : impôts retenus à la source',
        '471'  => 'Comptes d’attente',
        '6055' => 'Fournitures de bureau',
        '6052' => 'Eau, électricité',
        '622'  => 'Locations et charges locatives',
        '624'  => 'Entretien, réparations et maintenance',
        '626'  => 'Frais de télécommunications',
        '627'  => 'Publicité, publications, relations publiques',
        '631'  => 'Frais bancaires',
        '6324' => 'Honoraires (comptable, commissaire aux comptes)',
        '638'  => 'Autres charges externes (formation, réunions, AG)',
        '641'  => 'Impôts et taxes',
        '661'  => 'Rémunérations du personnel',
        '664'  => 'Charges sociales',
        '681'  => 'Dotations aux amortissements',
        '711'  => 'Subventions d’exploitation',
        '7581' => 'Dons et libéralités reçus',
        '771'  => 'Revenus financiers (intérêts)',
    ];
}
