<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Disaster-recovery drill log (CDC Phase 2, §28). Central — DR is an
 * infrastructure/operator concern spanning the platform and its tenants.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('dr_tests', function (Blueprint $t) {
            $t->id();
            $t->string('ref', 24)->unique();
            $t->enum('type', ['backup_restore', 'failover', 'full_dr', 'tabletop'])->default('backup_restore');
            $t->string('scope', 200);                 // e.g. "central + all tenants", "tenant mut_01"
            $t->date('performed_on');
            $t->unsignedInteger('rto_target_minutes')->nullable();
            $t->unsignedInteger('rto_actual_minutes')->nullable();
            $t->unsignedInteger('rpo_target_minutes')->nullable();
            $t->unsignedInteger('rpo_actual_minutes')->nullable();
            $t->enum('outcome', ['success', 'partial', 'failed'])->default('success');
            $t->text('findings')->nullable();
            $t->string('performed_by', 120)->nullable();
            $t->timestamps();
            $t->index(['type', 'outcome']);
        });
    }

    public function down(): void { Schema::connection('central')->dropIfExists('dr_tests'); }
};
