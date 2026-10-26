<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Réseau social « façon Facebook » (base CENTRALE) — migration additive :
 *  - pages créées par les membres (propriétaire d'une fiche d'organisation) ;
 *  - aperçu des liens publiés ;
 *  - mentions (@) dans les publications et les commentaires ;
 *  - vidéos et réels (médias de type vidéo, envois par morceaux).
 * Les tables du réseau sont aussi passées en utf8mb4 si besoin (emojis).
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        $s = Schema::connection('central');

        if (! $s->hasColumn('mutual_profiles', 'owner_account_id')) {
            $s->table('mutual_profiles', function (Blueprint $t) {
                $t->foreignId('owner_account_id')->nullable()->after('mutual_id')->constrained('public_accounts')->nullOnDelete();
                $t->index('owner_account_id');
            });
        }

        if (! $s->hasColumn('social_posts', 'link_title')) {
            $s->table('social_posts', function (Blueprint $t) {
                $t->string('link_title', 255)->nullable()->after('link_url');
                $t->string('link_description', 500)->nullable()->after('link_title');
                $t->string('link_image_path')->nullable()->after('link_description');
                $t->string('link_site', 120)->nullable()->after('link_image_path');
                $t->foreignId('posted_by_account_id')->nullable()->after('tenant_user_id')->constrained('public_accounts')->nullOnDelete();
            });
        }

        if (! $s->hasColumn('social_post_media', 'kind')) {
            $s->table('social_post_media', function (Blueprint $t) {
                $t->string('kind', 10)->default('image')->after('post_id');      // image | video
                $t->unsignedInteger('duration')->nullable()->after('height');    // secondes (vidéos)
            });
        }

        if (! $s->hasTable('social_mentions')) {
            $s->create('social_mentions', function (Blueprint $t) {
                $t->id();
                $t->foreignId('post_id')->nullable()->constrained('social_posts')->cascadeOnDelete();
                $t->foreignId('comment_id')->nullable()->constrained('social_comments')->cascadeOnDelete();
                $t->foreignId('public_account_id')->nullable()->constrained('public_accounts')->cascadeOnDelete();
                $t->string('mutual_id')->nullable();
                $t->foreign('mutual_id')->references('id')->on('mutuals')->cascadeOnDelete();
                $t->string('label', 180);
                $t->timestamps();
                $t->index('post_id');
                $t->index('comment_id');
                $t->index('public_account_id');
            });
        }

        if (! $s->hasTable('social_uploads')) {
            $s->create('social_uploads', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->foreignId('public_account_id')->constrained('public_accounts')->cascadeOnDelete();
                $t->string('original_name', 180)->nullable();
                $t->string('mime', 60);
                $t->unsignedBigInteger('size');
                $t->unsignedBigInteger('received')->default(0);
                $t->string('status', 20)->default('uploading');                 // uploading | complete | used
                $t->timestamps();
                $t->index(['public_account_id', 'status']);
            });
        }

        // Emojis : tables du réseau en utf8mb4 (sans effet si c'est déjà le cas).
        if (in_array(DB::connection('central')->getDriverName(), ['mysql', 'mariadb'], true)) {
            $db = DB::connection('central')->getDatabaseName();
            $tables = DB::connection('central')->select(
                "SELECT table_name AS t FROM information_schema.tables WHERE table_schema = ? AND table_collation NOT LIKE 'utf8mb4%'
                 AND (table_name LIKE 'social\\_%' OR table_name IN ('mutual_profiles', 'mutuals', 'public_accounts'))", [$db]);
            foreach ($tables as $row) {
                DB::connection('central')->statement('ALTER TABLE `' . str_replace('`', '', $row->t) . '` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            }
        }
    }

    public function down(): void
    {
        $s = Schema::connection('central');
        $s->dropIfExists('social_uploads');
        $s->dropIfExists('social_mentions');
        if ($s->hasColumn('social_post_media', 'kind')) {
            $s->table('social_post_media', fn (Blueprint $t) => $t->dropColumn(['kind', 'duration']));
        }
        if ($s->hasColumn('social_posts', 'link_title')) {
            $s->table('social_posts', function (Blueprint $t) {
                $t->dropConstrainedForeignId('posted_by_account_id');
                $t->dropColumn(['link_title', 'link_description', 'link_image_path', 'link_site']);
            });
        }
        if ($s->hasColumn('mutual_profiles', 'owner_account_id')) {
            $s->table('mutual_profiles', fn (Blueprint $t) => $t->dropConstrainedForeignId('owner_account_id'));
        }
    }
};
