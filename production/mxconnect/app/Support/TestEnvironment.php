<?php

namespace App\Support;

use App\Models\Central\Mutual;

/**
 * MUTUELLE TEST (mission « Mutuelle paramétrable », 2026-11-18) : une mutuelle marquée « test » par
 * le super-administrateur (attribut is_test de la mutuelle). Seule une mutuelle de test peut
 * recevoir les formules d'exemple ; ses données sont signalées par un bandeau « Environnement de test ».
 */
final class TestEnvironment
{
    /** La mutuelle en cours (contexte tenant) est-elle la mutuelle de test ? */
    public static function active(): bool
    {
        return function_exists('tenant') && tenant() && (bool) tenant('is_test');
    }

    public static function of(Mutual $mutual): bool
    {
        return (bool) ($mutual->is_test ?? false);
    }
}
