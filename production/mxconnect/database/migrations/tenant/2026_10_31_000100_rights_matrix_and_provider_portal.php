<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Base de CHAQUE mutuelle — migration additive du 2026-10-31 (matrice des droits + portail prestataire) :
 *  - rôle « auditor » (auditeur / contrôle interne, lecture seule) ;
 *  - users.care_provider_id : compte d'un prestataire de soins (registre central care_providers) ;
 *  - prestations.captured_by / validated_by / source : « personne ne valide sa propre saisie »,
 *    et origine de la saisie (back-office ou portail du prestataire) ;
 *  - provider_invoices.provider_reference : numéro de facture du prestataire ;
 *  - provider_conventions : prestataires conventionnés par la mutuelle ;
 *  - rights_checks : journal des vérifications de droits faites par les prestataires.
 * Aucune donnée supprimée.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'care_provider_id')) {
            Schema::table('users', function (Blueprint $t) {
                $t->unsignedBigInteger('care_provider_id')->nullable()->after('antenna_id')->index();
            });
        }
        if (! Schema::hasColumn('prestations', 'captured_by')) {
            Schema::table('prestations', function (Blueprint $t) {
                $t->unsignedBigInteger('captured_by')->nullable()->after('status');
                $t->unsignedBigInteger('validated_by')->nullable()->after('captured_by');
                $t->string('source', 12)->default('mutual')->after('validated_by');
                $t->index(['provider_id', 'status']);
            });
        }
        if (! Schema::hasColumn('provider_invoices', 'provider_reference')) {
            Schema::table('provider_invoices', function (Blueprint $t) {
                $t->string('provider_reference', 60)->nullable()->after('provider_id');
                $t->index(['provider_id', 'status']);
            });
        }
        if (! Schema::hasTable('provider_conventions')) {
            Schema::create('provider_conventions', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('care_provider_id')->unique();   // registre central
                $t->string('reference', 60)->nullable();                // n° de convention
                $t->date('starts_on')->nullable();
                $t->date('ends_on')->nullable();
                $t->boolean('active')->default(true);
                $t->string('contact_name', 120)->nullable();
                $t->string('contact_phone', 30)->nullable();
                $t->text('notes')->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('rights_checks')) {
            Schema::create('rights_checks', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id');
                $t->unsignedBigInteger('care_provider_id');
                $t->unsignedBigInteger('member_id')->nullable();
                $t->boolean('ok')->default(false);
                $t->json('reasons')->nullable();
                $t->timestamp('created_at')->nullable();
                $t->index(['care_provider_id', 'created_at']);
            });
        }

        // Rôle « auditor » dans les mutuelles déjà ouvertes (les nouvelles l'ont par le seeder).
        if (Schema::hasTable('roles') && ! DB::table('roles')->where('name', 'auditor')->where('guard_name', 'web')->exists()) {
            DB::table('roles')->insert(['name' => 'auditor', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('rights_checks');
        Schema::dropIfExists('provider_conventions');
        if (Schema::hasColumn('provider_invoices', 'provider_reference')) {
            Schema::table('provider_invoices', function (Blueprint $t) {
                $t->dropIndex(['provider_id', 'status']);
                $t->dropColumn('provider_reference');
            });
        }
        if (Schema::hasColumn('prestations', 'captured_by')) {
            Schema::table('prestations', function (Blueprint $t) {
                $t->dropIndex(['provider_id', 'status']);
                $t->dropColumn(['captured_by', 'validated_by', 'source']);
            });
        }
        if (Schema::hasColumn('users', 'care_provider_id')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('care_provider_id'));
        }
        // Le rôle « auditor » est conservé (des comptes peuvent l'avoir).
    }
};
