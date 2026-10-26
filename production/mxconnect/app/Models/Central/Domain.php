<?php

namespace App\Models\Central;

use Stancl\Tenancy\Database\Models\Domain as BaseDomain;

/**
 * Domaine (sous-domaine) d'une mutuelle.
 *
 * La table `domains` de MX-CONNECT utilise la colonne `mutual_id` (et non
 * `tenant_id`, convention par défaut de stancl/tenancy). Sans ce modèle, la
 * création du domaine d'une mutuelle échoue (Unknown column 'tenant_id') et
 * la résolution tenant par sous-domaine ne trouve rien.
 */
class Domain extends BaseDomain
{
    protected $table = 'domains';

    public function tenant()
    {
        return $this->belongsTo(config('tenancy.tenant_model'), 'mutual_id');
    }
}
