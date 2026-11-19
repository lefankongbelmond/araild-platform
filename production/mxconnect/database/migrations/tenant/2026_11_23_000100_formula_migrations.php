<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Base de CHAQUE mutuelle — changement de formule contrôlé (mission « Mutuelle paramétrable »,
 * Partie 5.2 ; 2026-11-23). ADDITIVE :
 *  - subscriptions.ends_on : fin d'une souscription remplacée (elle reste « validée » pour les
 *    soins antérieurs : aucune prise en charge passée n'est modifiée) ;
 *  - subscriptions.migrated_from_id : souscription d'origine ;
 *  - subscriptions.waiting_from : début de l'ancienneté retenu pour les délais de carence
 *    (ancienneté conservée lors d'un changement de formule) ;
 *  - formula_migrations : demandes de changement de formule (« 4 yeux »), avec leur résultat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $t) {
            if (! Schema::hasColumn('subscriptions', 'ends_on')) {
                $t->date('ends_on')->nullable()->after('effective_date');
            }
            if (! Schema::hasColumn('subscriptions', 'migrated_from_id')) {
                $t->unsignedBigInteger('migrated_from_id')->nullable()->after('ends_on');
            }
            if (! Schema::hasColumn('subscriptions', 'waiting_from')) {
                $t->date('waiting_from')->nullable()->after('migrated_from_id');
            }
        });

        if (! Schema::hasTable('formula_migrations')) {
            Schema::create('formula_migrations', function (Blueprint $t) {
                $t->id();
                $t->string('reference', 20)->nullable()->unique();
                $t->unsignedBigInteger('source_guarantee_id');
                $t->unsignedBigInteger('target_guarantee_id');
                $t->date('effective_date');
                $t->boolean('keep_seniority')->default(true);
                $t->json('member_ids')->nullable();          // null = tous les adhérents éligibles
                $t->text('note');
                $t->string('status', 20)->default('requested'); // requested, approved, rejected, applied, cancelled
                $t->unsignedBigInteger('requested_by');
                $t->unsignedBigInteger('decided_by')->nullable();
                $t->timestamp('decided_at')->nullable();
                $t->text('decision_note')->nullable();
                $t->timestamp('applied_at')->nullable();
                $t->json('result')->nullable();               // adhérents migrés et écartés (avec le motif)
                $t->timestamps();
                $t->index(['status', 'effective_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('formula_migrations');
        foreach (['waiting_from', 'migrated_from_id', 'ends_on'] as $c) {
            if (Schema::hasColumn('subscriptions', $c)) {
                Schema::table('subscriptions', fn (Blueprint $t) => $t->dropColumn($c));
            }
        }
    }
};
