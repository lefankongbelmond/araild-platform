<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Base de CHAQUE mutuelle — Portail prestataire V2, phase 3 (2026-11-17), migration additive :
 *  - rights_checks.dependent_id : bénéficiaire vérifié quand c'est un ayant droit ;
 *  - rights_checks.method : moyen d'identification (code, qr, search, central) — traçabilité ;
 *  - beneficiary_searches : journal des recherches par nom ou téléphone faites par les
 *    prestataires (qui, quand, critères, nombre de résultats). Rien n'est supprimé ;
 *  - resynchronisation de l'index central provider_affiliations (approved / suspended) à partir
 *    des conventions de la mutuelle : corrige les conventions créées en 2026.11.16, indexées
 *    « suspendues » à tort (valeur par défaut « active » non relue).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('rights_checks', 'dependent_id')) {
            Schema::table('rights_checks', function (Blueprint $t) {
                $t->unsignedBigInteger('dependent_id')->nullable()->after('member_id');
                $t->string('method', 12)->nullable()->after('reasons');
            });
        }
        if (! Schema::hasTable('beneficiary_searches')) {
            Schema::create('beneficiary_searches', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id');
                $t->unsignedBigInteger('care_provider_id');
                $t->string('mode', 12);                 // name | phone
                $t->json('criteria');                   // critères saisis (données de la mutuelle, restent chez elle)
                $t->unsignedSmallInteger('results')->default(0);
                $t->timestamp('created_at')->nullable();
                $t->index(['care_provider_id', 'created_at']);
            });
        }

        $mutualId = function_exists('tenant') ? tenant('id') : null;
        if ($mutualId && Schema::hasTable('provider_conventions') && Schema::connection('central')->hasTable('provider_affiliations')) {
            foreach (\Illuminate\Support\Facades\DB::table('provider_conventions')->get(['care_provider_id', 'active']) as $c) {
                \Illuminate\Support\Facades\DB::connection('central')->table('provider_affiliations')
                    ->where('care_provider_id', $c->care_provider_id)->where('mutual_id', $mutualId)->whereIn('status', ['approved', 'suspended'])
                    ->update(['status' => $c->active ? 'approved' : 'suspended', 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficiary_searches');
        if (Schema::hasColumn('rights_checks', 'dependent_id')) {
            Schema::table('rights_checks', fn (Blueprint $t) => $t->dropColumn(['dependent_id', 'method']));
        }
    }
};
