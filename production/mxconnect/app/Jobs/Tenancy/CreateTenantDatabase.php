<?php

namespace App\Jobs\Tenancy;

use App\Support\TenantStorage;
use Stancl\Tenancy\Database\DatabaseManager;
use Stancl\Tenancy\Jobs\CreateDatabase;

/** CreateDatabase de stancl, sauf pour une mutuelle à tables préfixées (sa base est la base centrale). */
class CreateTenantDatabase extends CreateDatabase
{
    public function handle(DatabaseManager $databaseManager)
    {
        if ($this->tenant instanceof \App\Models\Central\Mutual && TenantStorage::isPrefixed($this->tenant)) {
            return null;   // rien à créer ; null (et non false) laisse les migrations s'exécuter
        }

        return parent::handle($databaseManager);
    }
}
