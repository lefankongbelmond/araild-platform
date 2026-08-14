<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Governance registers (CDC Phase 2, §22–24): risk register, incident log,
 * complaint register. All tenant-scoped and auditable via their models.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risks', function (Blueprint $t) {
            $t->id();
            $t->string('ref', 24)->unique();
            $t->string('title', 200);
            $t->text('description')->nullable();
            $t->enum('category', ['strategic', 'operational', 'financial', 'compliance', 'security', 'other'])->default('operational');
            $t->unsignedTinyInteger('likelihood');   // 1..5
            $t->unsignedTinyInteger('impact');       // 1..5
            $t->text('treatment')->nullable();
            $t->string('owner_role', 60)->nullable();
            $t->enum('status', ['open', 'mitigating', 'closed'])->default('open');
            $t->date('opened_on');
            $t->date('review_on')->nullable();
            $t->date('closed_on')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['status', 'category']);
        });

        Schema::create('incidents', function (Blueprint $t) {
            $t->id();
            $t->string('ref', 24)->unique();
            $t->string('title', 200);
            $t->text('description')->nullable();
            $t->enum('category', ['operational', 'security', 'fraud', 'data_breach', 'service', 'other'])->default('operational');
            $t->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $t->date('occurred_on');
            $t->date('detected_on')->nullable();
            $t->enum('status', ['open', 'investigating', 'resolved', 'closed'])->default('open');
            $t->text('resolution')->nullable();
            $t->date('resolved_on')->nullable();
            $t->timestamps();
            $t->index(['status', 'severity']);
        });

        Schema::create('complaints', function (Blueprint $t) {
            $t->id();
            $t->string('ref', 24)->unique();
            $t->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();
            $t->enum('channel', ['phone', 'in_person', 'written', 'other'])->default('in_person');
            $t->string('subject', 200);
            $t->text('description')->nullable();
            $t->enum('status', ['received', 'in_progress', 'resolved', 'closed'])->default('received');
            $t->date('received_on');
            $t->date('due_on')->nullable();          // SLA deadline
            $t->text('resolution')->nullable();
            $t->date('resolved_on')->nullable();
            $t->timestamps();
            $t->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaints');
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('risks');
    }
};
