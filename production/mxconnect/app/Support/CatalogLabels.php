<?php

namespace App\Support;

use App\Models\Tenant\GuaranteeVersion;

/** Libellés du catalogue des formules (périodicités, statuts). */
final class CatalogLabels
{
    public const PERIODICITIES = ['monthly' => 'Mensuelle', 'quarterly' => 'Trimestrielle', 'semiannual' => 'Semestrielle', 'annual' => 'Annuelle', 'custom' => 'Personnalisée'];
    public const STATUSES = ['draft' => 'Brouillon', 'test' => 'En test', 'active' => 'Active', 'suspended' => 'Suspendue', 'archived' => 'Archivée'];

    public static function periodicity(GuaranteeVersion $v): string
    {
        if ($v->periodicity === 'custom') {
            return $v->period_months ? "tous les {$v->period_months} mois" : 'personnalisée (mois non définis)';
        }

        return mb_strtolower(self::PERIODICITIES[$v->periodicity] ?? $v->periodicity);
    }
}
