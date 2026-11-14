<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Base de CHAQUE mutuelle — portail adhérent (mission « Mutuelle paramétrable », L4, 2026-11-20). ADDITIVE :
 *  - member_change_requests : changements demandés par l'adhérent depuis son portail (coordonnées,
 *    ajout / modification / retrait d'un ayant droit), appliqués seulement après validation ;
 *  - complaints : canal « en ligne », catégorie, compte demandeur ; complaint_messages : échanges.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('member_change_requests')) {
            Schema::create('member_change_requests', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('member_id')->index();
                $t->unsignedBigInteger('public_account_id')->nullable();
                $t->string('kind', 20);                     // contact | dependent_add | dependent_update | dependent_remove
                $t->unsignedBigInteger('dependent_id')->nullable();
                $t->json('payload');
                $t->string('status', 10)->default('requested');   // requested | approved | rejected | cancelled
                $t->unsignedBigInteger('decided_by')->nullable();
                $t->timestamp('decided_at')->nullable();
                $t->text('decision_note')->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasColumn('complaints', 'category')) {
            $table = DB::connection()->getQueryGrammar()->wrapTable('complaints');
            DB::statement("ALTER TABLE {$table} MODIFY channel ENUM('phone','in_person','written','other','online') NOT NULL DEFAULT 'in_person'");
            Schema::table('complaints', function (Blueprint $t) {
                $t->string('category', 20)->nullable();           // complaint | provider_issue | information
                $t->unsignedBigInteger('public_account_id')->nullable();
            });
        }
        if (! Schema::hasTable('complaint_messages')) {
            Schema::create('complaint_messages', function (Blueprint $t) {
                $t->id();
                $t->foreignId('complaint_id')->constrained('complaints')->cascadeOnDelete();
                $t->string('author', 8);                     // member | staff
                $t->unsignedBigInteger('user_id')->nullable();
                $t->text('body');
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('complaint_messages');
        Schema::dropIfExists('member_change_requests');
        $cols = array_values(array_filter(['category', 'public_account_id'], fn ($c) => Schema::hasColumn('complaints', $c)));
        if ($cols) {
            Schema::table('complaints', fn (Blueprint $t) => $t->dropColumn($cols));
        }
        // Le canal « online » est conservé (des réclamations peuvent l'utiliser).
    }
};
