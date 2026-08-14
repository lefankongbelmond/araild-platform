<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ConfigReferenceSeeder::class,  // countries/currencies/locales/providers
            NetworkSeeder::class,          // first super admin
        ]);
        // Pilot mutuals are created from the admin UI (or a DemoTenantSeeder in non-prod),
        // NEVER hard-coded here.
    }
}
