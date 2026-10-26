<?php

namespace App\Support;

/**
 * Domaines d'intervention du réseau (repris du Lot 2) : filtres de « Découvrir »,
 * de la navigation et des listes. Stockés sous forme de codes (JSON) sur les
 * fiches d'organisation et les projets. Ajouter un domaine = une ligne ici + une icône « d-<code> ».
 */
class SocialDomains
{
    public const ALL = [
        'sante'              => 'Santé',
        'protection_sociale' => 'Protection sociale',
        'education'          => 'Éducation',
        'agriculture'        => 'Agriculture',
        'environnement'      => 'Environnement',
        'entrepreneuriat'    => 'Entrepreneuriat',
        'solidarite'         => 'Solidarité',
    ];

    public static function label(?string $code): ?string
    {
        return self::ALL[$code] ?? null;
    }

    /** Ne garde que des codes connus, sans doublon, dans l'ordre de la liste. */
    public static function clean(?array $codes): array
    {
        return array_values(array_intersect(array_keys(self::ALL), (array) $codes));
    }

    public static function valid(?string $code): ?string
    {
        return array_key_exists((string) $code, self::ALL) ? $code : null;
    }

    /** Filtre « JSON_CONTAINS » portable (MariaDB / MySQL) sur une colonne JSON de codes. */
    public static function whereHasDomain($query, string $column, string $code)
    {
        return $query->where($column, 'like', '%"' . $code . '"%');
    }
}
