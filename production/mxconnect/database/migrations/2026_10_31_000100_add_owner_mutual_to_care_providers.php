<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CENTRALE — registre des prestataires de soins : une mutuelle peut y ajouter un
 * prestataire (« Prestataires conventionnés »). created_by_mutual = identifiant de la
 * mutuelle qui l'a créé : elle seule peut modifier sa fiche (les autres l'utilisent).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('care_providers', 'created_by_mutual')) {
            Schema::table('care_providers', function (Blueprint $t) {
                $t->string('created_by_mutual', 64)->nullable()->after('active')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('care_providers', 'created_by_mutual')) {
            Schema::table('care_providers', fn (Blueprint $t) => $t->dropColumn('created_by_mutual'));
        }
    }
};
