<?php

namespace App\Support;

/**
 * Liste de référence des pays (2026-11-02) : les 54 pays d'Afrique et quelques pays
 * partenaires, avec leur devise (ISO 4217), leur indicatif téléphonique et la langue
 * d'interface proposée par défaut (MX-CONNECT est traduit en français et en anglais).
 * Ajouter un pays depuis cette liste = insérer des DONNÉES (aucune modification de code).
 */
class CountryCatalog
{
    /** région => [[iso2, nom, devise, indicatif, langue], …] */
    public const REGIONS = [
        'Afrique centrale' => [
            ['CM', 'Cameroun', 'XAF', '+237', 'fr'], ['CF', 'République centrafricaine', 'XAF', '+236', 'fr'],
            ['TD', 'Tchad', 'XAF', '+235', 'fr'], ['CG', 'Congo', 'XAF', '+242', 'fr'], ['GA', 'Gabon', 'XAF', '+241', 'fr'],
            ['GQ', 'Guinée équatoriale', 'XAF', '+240', 'fr'], ['CD', 'République démocratique du Congo', 'CDF', '+243', 'fr'],
            ['BI', 'Burundi', 'BIF', '+257', 'fr'], ['RW', 'Rwanda', 'RWF', '+250', 'en'],
            ['ST', 'Sao Tomé-et-Principe', 'STN', '+239', 'fr'], ['AO', 'Angola', 'AOA', '+244', 'en'],
        ],
        'Afrique de l’Ouest' => [
            ['BJ', 'Bénin', 'XOF', '+229', 'fr'], ['BF', 'Burkina Faso', 'XOF', '+226', 'fr'], ['CI', 'Côte d’Ivoire', 'XOF', '+225', 'fr'],
            ['GW', 'Guinée-Bissau', 'XOF', '+245', 'fr'], ['ML', 'Mali', 'XOF', '+223', 'fr'], ['NE', 'Niger', 'XOF', '+227', 'fr'],
            ['SN', 'Sénégal', 'XOF', '+221', 'fr'], ['TG', 'Togo', 'XOF', '+228', 'fr'], ['GN', 'Guinée', 'GNF', '+224', 'fr'],
            ['MR', 'Mauritanie', 'MRU', '+222', 'fr'], ['CV', 'Cap-Vert', 'CVE', '+238', 'en'], ['GM', 'Gambie', 'GMD', '+220', 'en'],
            ['GH', 'Ghana', 'GHS', '+233', 'en'], ['LR', 'Liberia', 'LRD', '+231', 'en'], ['NG', 'Nigeria', 'NGN', '+234', 'en'],
            ['SL', 'Sierra Leone', 'SLE', '+232', 'en'],
        ],
        'Afrique de l’Est et océan Indien' => [
            ['KE', 'Kenya', 'KES', '+254', 'en'], ['TZ', 'Tanzanie', 'TZS', '+255', 'en'], ['UG', 'Ouganda', 'UGX', '+256', 'en'],
            ['ET', 'Éthiopie', 'ETB', '+251', 'en'], ['DJ', 'Djibouti', 'DJF', '+253', 'fr'], ['ER', 'Érythrée', 'ERN', '+291', 'en'],
            ['SO', 'Somalie', 'SOS', '+252', 'en'], ['SS', 'Soudan du Sud', 'SSP', '+211', 'en'], ['SD', 'Soudan', 'SDG', '+249', 'en'],
            ['KM', 'Comores', 'KMF', '+269', 'fr'], ['MG', 'Madagascar', 'MGA', '+261', 'fr'], ['MU', 'Maurice', 'MUR', '+230', 'en'],
            ['SC', 'Seychelles', 'SCR', '+248', 'en'],
        ],
        'Afrique australe' => [
            ['ZA', 'Afrique du Sud', 'ZAR', '+27', 'en'], ['BW', 'Botswana', 'BWP', '+267', 'en'], ['LS', 'Lesotho', 'LSL', '+266', 'en'],
            ['SZ', 'Eswatini', 'SZL', '+268', 'en'], ['NA', 'Namibie', 'NAD', '+264', 'en'], ['MZ', 'Mozambique', 'MZN', '+258', 'en'],
            ['MW', 'Malawi', 'MWK', '+265', 'en'], ['ZM', 'Zambie', 'ZMW', '+260', 'en'], ['ZW', 'Zimbabwe', 'USD', '+263', 'en'],
        ],
        'Afrique du Nord' => [
            ['DZ', 'Algérie', 'DZD', '+213', 'fr'], ['MA', 'Maroc', 'MAD', '+212', 'fr'], ['TN', 'Tunisie', 'TND', '+216', 'fr'],
            ['LY', 'Libye', 'LYD', '+218', 'en'], ['EG', 'Égypte', 'EGP', '+20', 'en'],
        ],
        'Europe et Amériques' => [
            ['FR', 'France', 'EUR', '+33', 'fr'], ['BE', 'Belgique', 'EUR', '+32', 'fr'], ['CH', 'Suisse', 'CHF', '+41', 'fr'],
            ['CA', 'Canada', 'CAD', '+1', 'fr'], ['HT', 'Haïti', 'HTG', '+509', 'fr'], ['US', 'États-Unis', 'USD', '+1', 'en'],
        ],
    ];

    /** Devises créées si absentes : code => [nom, symbole, décimales]. */
    public const CURRENCIES = [
        'XAF' => ['Franc CFA (CEMAC)', 'FCFA', 0], 'XOF' => ['Franc CFA (UEMOA)', 'FCFA', 0], 'CDF' => ['Franc congolais', 'FC', 2],
        'BIF' => ['Franc burundais', 'FBu', 0], 'RWF' => ['Franc rwandais', 'FRw', 0], 'STN' => ['Dobra', 'Db', 2], 'AOA' => ['Kwanza', 'Kz', 2],
        'GNF' => ['Franc guinéen', 'FG', 0], 'MRU' => ['Ouguiya', 'UM', 2], 'CVE' => ['Escudo cap-verdien', 'Esc', 2], 'GMD' => ['Dalasi', 'D', 2],
        'GHS' => ['Cedi', 'GH₵', 2], 'LRD' => ['Dollar libérien', 'L$', 2], 'NGN' => ['Naira', '₦', 2], 'SLE' => ['Leone', 'Le', 2],
        'KES' => ['Shilling kényan', 'KSh', 2], 'TZS' => ['Shilling tanzanien', 'TSh', 2], 'UGX' => ['Shilling ougandais', 'USh', 0],
        'ETB' => ['Birr', 'Br', 2], 'DJF' => ['Franc djiboutien', 'Fdj', 0], 'ERN' => ['Nakfa', 'Nfk', 2], 'SOS' => ['Shilling somalien', 'Sh', 2],
        'SSP' => ['Livre sud-soudanaise', 'SSP', 2], 'SDG' => ['Livre soudanaise', 'LS', 2], 'KMF' => ['Franc comorien', 'FC', 0],
        'MGA' => ['Ariary', 'Ar', 2], 'MUR' => ['Roupie mauricienne', 'Rs', 2], 'SCR' => ['Roupie seychelloise', 'SR', 2],
        'ZAR' => ['Rand', 'R', 2], 'BWP' => ['Pula', 'P', 2], 'LSL' => ['Loti', 'L', 2], 'SZL' => ['Lilangeni', 'E', 2],
        'NAD' => ['Dollar namibien', 'N$', 2], 'MZN' => ['Metical', 'MT', 2], 'MWK' => ['Kwacha malawien', 'MK', 2],
        'ZMW' => ['Kwacha zambien', 'K', 2], 'USD' => ['Dollar américain', '$', 2], 'DZD' => ['Dinar algérien', 'DA', 2],
        'MAD' => ['Dirham marocain', 'DH', 2], 'TND' => ['Dinar tunisien', 'DT', 3], 'LYD' => ['Dinar libyen', 'LD', 3],
        'EGP' => ['Livre égyptienne', 'E£', 2], 'EUR' => ['Euro', '€', 2], 'CHF' => ['Franc suisse', 'CHF', 2],
        'CAD' => ['Dollar canadien', 'CA$', 2], 'HTG' => ['Gourde', 'G', 2],
    ];

    public const LOCALES = ['fr' => 'Français', 'en' => 'English'];

    /** iso2 => [iso2, nom, devise, indicatif, langue, région] */
    public static function all(): array
    {
        $out = [];
        foreach (self::REGIONS as $region => $rows) {
            foreach ($rows as $r) {
                $out[$r[0]] = [...$r, $region];
            }
        }

        return $out;
    }

    public static function find(string $iso2): ?array
    {
        return self::all()[strtoupper($iso2)] ?? null;
    }
}
