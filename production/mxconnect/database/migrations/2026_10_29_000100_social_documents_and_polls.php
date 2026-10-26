<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Réseau social (base CENTRALE) — migration additive « publications enrichies » :
 *  - documents PDF PUBLICS joints aux publications d'une page ou à un projet (rapports, affiches…) ;
 *  - sondages dans le fil (2 à 4 choix, durée limitée, résultats anonymes).
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        $s = Schema::connection('central');

        if (! $s->hasTable('social_documents')) {
            $s->create('social_documents', function (Blueprint $t) {
                $t->id();
                $t->foreignId('post_id')->nullable()->constrained('social_posts')->cascadeOnDelete();
                $t->foreignId('project_id')->nullable()->constrained('social_projects')->cascadeOnDelete();
                $t->string('mutual_id');
                $t->foreign('mutual_id')->references('id')->on('mutuals')->cascadeOnDelete();
                $t->string('title', 180);
                $t->string('path');                                  // disque « social » (public), servi par /media
                $t->string('original_name', 180)->nullable();
                $t->unsignedInteger('size')->default(0);
                $t->unsignedInteger('downloads_count')->default(0);
                $t->foreignId('uploaded_by_account_id')->nullable()->constrained('public_accounts')->nullOnDelete();
                $t->unsignedBigInteger('tenant_user_id')->nullable();
                $t->timestamps();
                $t->index('post_id');
                $t->index('project_id');
            });
        }

        if (! $s->hasTable('social_polls')) {
            $s->create('social_polls', function (Blueprint $t) {
                $t->id();
                $t->foreignId('post_id')->unique()->constrained('social_posts')->cascadeOnDelete();
                $t->timestamp('closes_at')->nullable();
                $t->unsignedInteger('votes_count')->default(0);
                $t->timestamps();
            });
            $s->create('social_poll_options', function (Blueprint $t) {
                $t->id();
                $t->foreignId('poll_id')->constrained('social_polls')->cascadeOnDelete();
                $t->string('label', 120);
                $t->unsignedTinyInteger('position')->default(0);
                $t->unsignedInteger('votes_count')->default(0);
            });
            $s->create('social_poll_votes', function (Blueprint $t) {
                $t->id();
                $t->foreignId('poll_id')->constrained('social_polls')->cascadeOnDelete();
                $t->foreignId('option_id')->constrained('social_poll_options')->cascadeOnDelete();
                $t->foreignId('public_account_id')->constrained('public_accounts')->cascadeOnDelete();
                $t->timestamps();
                $t->unique(['poll_id', 'public_account_id']);    // un seul choix par personne (modifiable)
            });
        }
    }

    public function down(): void
    {
        $s = Schema::connection('central');
        foreach (['social_poll_votes', 'social_poll_options', 'social_polls', 'social_documents'] as $table) {
            $s->dropIfExists($table);
        }
    }
};
