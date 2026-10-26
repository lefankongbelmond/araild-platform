<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Réseau social — livraison 3 (base CENTRALE) : groupes communautaires animés
 * par une mutuelle. Additif et réversible.
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        $s = Schema::connection('central');

        $s->create('social_groups', function (Blueprint $t) {
            $t->id();
            $t->string('mutual_id');                          // mutuelle qui anime et modère le groupe
            $t->foreign('mutual_id')->references('id')->on('mutuals')->cascadeOnDelete();
            $t->string('slug', 80)->unique();
            $t->string('name', 120);
            $t->string('topic', 40)->nullable();              // thème (catégories d'activité)
            $t->text('description')->nullable();
            $t->text('rules')->nullable();
            $t->string('cover_path')->nullable();
            $t->string('visibility', 20)->default('public');  // public | private (adhésion sur approbation)
            $t->string('status', 20)->default('active');      // active | archived
            $t->unsignedInteger('members_count')->default(0);
            $t->unsignedInteger('posts_count')->default(0);
            $t->boolean('is_demo')->default(false);
            $t->timestamps();
            $t->index(['status', 'visibility']);
        });

        $s->create('social_group_members', function (Blueprint $t) {
            $t->id();
            $t->foreignId('group_id')->constrained('social_groups')->cascadeOnDelete();
            $t->foreignId('public_account_id')->constrained('public_accounts')->cascadeOnDelete();
            $t->string('status', 20)->default('active');      // active | pending
            $t->timestamps();
            $t->unique(['group_id', 'public_account_id']);
            $t->index(['public_account_id', 'status']);
        });

        $s->table('social_posts', function (Blueprint $t) {
            $t->foreignId('group_id')->nullable()->after('public_account_id')->constrained('social_groups')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        $s = Schema::connection('central');
        $s->table('social_posts', function (Blueprint $t) {
            $t->dropConstrainedForeignId('group_id');
        });
        $s->dropIfExists('social_group_members');
        $s->dropIfExists('social_groups');
    }
};
