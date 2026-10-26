<?php

namespace App\Jobs\Tenancy;

use App\Support\TenantStorage;
use Stancl\Tenancy\Jobs\DeleteDatabase;

/** DeleteDatabase de stancl : pour une mutuelle à préfixe, on ne supprime QUE ses tables, jamais la base centrale. */
class DeleteTenantDatabase extends DeleteDatabase
{
    public function handle()
    {
        if ($this->tenant instanceof \App\Models\Central\Mutual && TenantStorage::isPrefixed($this->tenant)) {
            TenantStorage::dropTables(TenantStorage::prefix($this->tenant));

            return;
        }

        parent::handle();
    }
}
