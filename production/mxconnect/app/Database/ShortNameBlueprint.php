<?php

namespace App\Database;

use Illuminate\Database\Schema\Blueprint;

/**
 * Noms d'index et de clés étrangères limités à 64 caractères (limite MySQL/MariaDB).
 *
 * Avec les tables préfixées (mut03_…), certains noms générés par Laravel dépassent 64
 * caractères. Un nom trop long est tronqué et complété d'une empreinte courte : le nom
 * reste unique, stable (dropForeign(['colonne']) le retrouve) et contient le préfixe
 * de la mutuelle. Les noms de 64 caractères ou moins sont strictement inchangés.
 */
class ShortNameBlueprint extends Blueprint
{
    public const MAX = 64;

    protected function createIndexName($type, array $columns)
    {
        $name = parent::createIndexName($type, $columns);

        return strlen($name) <= self::MAX ? $name : substr($name, 0, self::MAX - 9) . '_' . substr(md5($name), 0, 8);
    }
}
