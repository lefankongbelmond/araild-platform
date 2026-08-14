<?php

namespace Database\Seeders;

use App\Models\Central\NetworkUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/** Seeds network roles + the first super admin (central). Rotate the password on first login. */
class NetworkSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('mxconnect.network_roles') as $role) {
            Role::findOrCreate($role, 'network');
        }

        $admin = NetworkUser::firstOrCreate(
            ['email' => 'admin@mx-connect.com'],
            [
                'first_name' => 'Super',
                'last_name'  => 'Admin',
                'password'   => Hash::make(env('SEED_ADMIN_PASSWORD', 'ChangeMe!2026')),
                'active'     => true,
            ]
        );

        $admin->syncRoles(['super_admin']);
    }
}
