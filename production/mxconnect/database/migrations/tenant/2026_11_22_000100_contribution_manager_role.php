<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Base de CHAQUE mutuelle — mission « Mutuelle paramétrable », livraison L6 (2026-11-22). ADDITIVE :
 * rôle « contribution_manager » (gestionnaire des cotisations) ajouté aux rôles de la mutuelle.
 * Ses droits sont dans App\Support\TenantAccess (lettre K). Aucune donnée modifiée.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('roles') && ! DB::table('roles')->where('name', 'contribution_manager')->where('guard_name', 'web')->exists()) {
            DB::table('roles')->insert(['name' => 'contribution_manager', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
            try {
                app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
            } catch (\Throwable) {
                // cache indisponible : il expirera de lui-même
            }
        }
    }

    public function down(): void
    {
        // Le rôle est conservé (des comptes peuvent l'avoir).
    }
};
