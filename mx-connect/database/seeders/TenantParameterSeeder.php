<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * Runs INSIDE a tenant DB at provisioning time. Installs:
 *  - mutual-level roles/permissions (maker-checker aware),
 *  - a minimal SYSCOHADA chart of accounts,
 *  - the 15-risk starter matrix (phase 2 table; harmless if absent).
 */
class TenantParameterSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('mxconnect.mutual_roles') as $role) {
            Role::findOrCreate($role, 'web');
        }

        // Example maker-checker permission split.
        Permission::findOrCreate('subscription.capture', 'web');
        Permission::findOrCreate('subscription.validate', 'web');
        Role::findByName('enrollment_agent')->givePermissionTo('subscription.capture');
        Role::findByName('controller_validator')->givePermissionTo('subscription.validate');

        // Claims chain (Module 9)
        Permission::findOrCreate('prestation.capture', 'web');
        Permission::findOrCreate('prestation.validate', 'web');
        Role::findByName('benefits_manager')->givePermissionTo('prestation.capture');
        Role::findByName('controller_validator')->givePermissionTo('prestation.validate');

        // SYSCOHADA chart — seeded from config so it matches AccountingService mapping.
        if (DB::getSchemaBuilder()->hasTable('accounts')) {
            foreach (config('mxconnect.accounting.chart', []) as $number => [$label, $class]) {
                DB::table('accounts')->updateOrInsert(
                    ['number' => (string) $number],
                    ['label' => $label, 'class' => $class, 'created_at' => now(), 'updated_at' => now()],
                );
            }
        }
    }
}
