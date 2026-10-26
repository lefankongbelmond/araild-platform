<?php

namespace App\Support;

use App\Models\Central\Mutual;
use Illuminate\Support\Facades\DB;

/**
 * Où sont stockées les données d'une mutuelle (2026-11-02).
 *
 *  - « base dédiée » : une base MySQL par mutuelle (VPS : créée automatiquement ;
 *    mutualisé : base créée dans le panneau et saisie à la création) ;
 *  - « tables préfixées » : hébergement mutualisé sans droit CREATE DATABASE (LWS).
 *    Les tables de la mutuelle sont créées AUTOMATIQUEMENT dans la base centrale, sous
 *    un préfixe qui lui est propre (ex. mut03_members). stancl/tenancy transmet
 *    l'attribut interne `tenancy_db_prefix` à la connexion « tenant » : toutes les
 *    requêtes Eloquent / Query Builder / Schema de la mutuelle n'atteignent que ses tables.
 */
class TenantStorage
{
    /** Préfixe des tables d'une mutuelle : mut_03 → « mut03_ ». */
    public static function prefixFor(string $id): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($id)) . '_';
    }

    public static function prefix(Mutual $mutual): ?string
    {
        $p = $mutual->getInternal('db_prefix');

        return is_string($p) && $p !== '' ? $p : null;
    }

    public static function isPrefixed(Mutual $mutual): bool
    {
        return self::prefix($mutual) !== null;
    }

    /** Libellé affiché dans l'espace super-admin. */
    public static function label(Mutual $mutual): string
    {
        return self::isPrefixed($mutual) ? 'Tables préfixées (' . self::prefix($mutual) . ')' : 'Base dédiée';
    }

    /** Tables de la base centrale portant ce préfixe. */
    public static function tables(string $prefix): array
    {
        $like = str_replace(['\\', '_', '%'], ['\\\\', '\\_', '\\%'], $prefix) . '%';

        return array_map(fn ($r) => array_values((array) $r)[0], DB::connection('central')->select(
            'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE ? ORDER BY TABLE_NAME', [$like]));
    }

    /** Supprime les tables d'une mutuelle à préfixe (provisioning interrompu). Jamais sans préfixe. */
    public static function dropTables(string $prefix): void
    {
        if (strlen($prefix) < 3 || ! str_ends_with($prefix, '_')) {
            throw new \InvalidArgumentException('Préfixe de tables invalide.');
        }
        $db = DB::connection('central');
        $db->statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach (self::tables($prefix) as $table) {
                $db->statement('DROP TABLE IF EXISTS `' . str_replace('`', '', $table) . '`');
            }
        } finally {
            $db->statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }
}
