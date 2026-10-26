<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Réseau social MX Connect — base CENTRALE (commune à toutes les mutuelles).
 *
 * Les bases isolées des mutuelles restent dédiées à la gestion (adhérents,
 * cotisations, prestations…). Le réseau public — vitrines, publications,
 * abonnements — doit être consultable à travers toutes les mutuelles : il vit
 * donc ici. Migration additive et réversible : aucune table existante n'est
 * supprimée, seules des colonnes nullables sont ajoutées à public_accounts.
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        $s = Schema::connection('central');

        // Vitrine publique d'une mutuelle (1-1 avec mutuals, sans toucher à la table tenancy).
        $s->create('mutual_profiles', function (Blueprint $t) {
            $t->string('mutual_id')->primary();
            $t->foreign('mutual_id')->references('id')->on('mutuals')->cascadeOnDelete();
            $t->string('category', 40)->default('mutuelle');      // mutuelle | organisation | partenaire
            $t->string('tagline', 180)->nullable();
            $t->text('description')->nullable();
            $t->text('mission')->nullable();
            $t->string('coverage_zone', 255)->nullable();
            $t->json('services')->nullable();
            $t->string('phone', 40)->nullable();
            $t->string('email', 180)->nullable();
            $t->string('website', 255)->nullable();
            $t->string('address', 255)->nullable();
            $t->string('logo_path')->nullable();
            $t->string('cover_path')->nullable();
            $t->boolean('is_demo')->default(false);
            $t->unsignedInteger('followers_count')->default(0);
            $t->unsignedInteger('posts_count')->default(0);
            $t->timestamps();
            $t->index(['category', 'is_demo']);
        });

        // Profil social des comptes grand public (colonnes nullables : aucune donnée existante touchée).
        $s->table('public_accounts', function (Blueprint $t) {
            $t->string('avatar_path')->nullable()->after('last_name');
            $t->string('bio', 280)->nullable()->after('avatar_path');
            $t->string('city', 120)->nullable()->after('bio');
            $t->json('interests')->nullable()->after('city');
            $t->string('profile_visibility', 20)->default('public')->after('interests');
            $t->boolean('is_demo')->default(false)->after('profile_visibility');
            $t->index('email');
        });

        $s->create('social_posts', function (Blueprint $t) {
            $t->id();
            $t->string('mutual_id')->nullable();                   // publication d'une organisation
            $t->foreign('mutual_id')->references('id')->on('mutuals')->cascadeOnDelete();
            $t->foreignId('public_account_id')->nullable()->constrained('public_accounts')->cascadeOnDelete();
            $t->unsignedBigInteger('tenant_user_id')->nullable();  // auteur côté back-office mutuelle
            $t->string('author_label', 180)->nullable();           // nom affiché (instantané)
            $t->string('type', 20)->default('texte');              // texte|annonce|campagne|activite|evenement|offre|document|lien
            $t->string('category', 40)->nullable();                // prévention, sensibilisation, formation…
            $t->text('body')->nullable();
            $t->string('link_url', 500)->nullable();
            $t->dateTime('event_starts_at')->nullable();
            $t->string('event_location', 180)->nullable();
            $t->string('visibility', 20)->default('public');       // public | followers
            $t->string('status', 20)->default('published');        // draft|pending|published|archived|hidden
            $t->foreignId('shared_post_id')->nullable()->constrained('social_posts')->nullOnDelete();
            $t->unsignedInteger('reactions_count')->default(0);
            $t->unsignedInteger('comments_count')->default(0);
            $t->unsignedInteger('shares_count')->default(0);
            $t->boolean('is_demo')->default(false);
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['status', 'visibility', 'published_at']);
            $t->index(['mutual_id', 'status', 'published_at']);
            $t->index(['public_account_id', 'published_at']);
            $t->index(['type', 'status']);
        });

        $s->create('social_post_media', function (Blueprint $t) {
            $t->id();
            $t->foreignId('post_id')->constrained('social_posts')->cascadeOnDelete();
            $t->string('path');
            $t->string('thumb_path')->nullable();
            $t->string('mime', 60);
            $t->unsignedInteger('width')->nullable();
            $t->unsignedInteger('height')->nullable();
            $t->unsignedInteger('size')->default(0);
            $t->unsignedTinyInteger('position')->default(0);
            $t->timestamps();
        });

        $s->create('social_reactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('post_id')->constrained('social_posts')->cascadeOnDelete();
            $t->foreignId('public_account_id')->constrained('public_accounts')->cascadeOnDelete();
            $t->string('type', 20)->default('jaime');              // jaime | soutien | bravo | utile
            $t->timestamps();
            $t->unique(['post_id', 'public_account_id']);
        });

        $s->create('social_comments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('post_id')->constrained('social_posts')->cascadeOnDelete();
            $t->foreignId('parent_id')->nullable()->constrained('social_comments')->cascadeOnDelete();
            $t->foreignId('public_account_id')->nullable()->constrained('public_accounts')->cascadeOnDelete();
            $t->string('mutual_id')->nullable();                   // réponse officielle d'une mutuelle
            $t->foreign('mutual_id')->references('id')->on('mutuals')->cascadeOnDelete();
            $t->string('author_label', 180)->nullable();
            $t->text('body');
            $t->string('status', 20)->default('visible');          // visible | hidden
            $t->timestamps();
            $t->softDeletes();
            $t->index(['post_id', 'status', 'created_at']);
        });

        $s->create('social_follows', function (Blueprint $t) {
            $t->id();
            $t->foreignId('public_account_id')->constrained('public_accounts')->cascadeOnDelete();
            $t->string('mutual_id');
            $t->foreign('mutual_id')->references('id')->on('mutuals')->cascadeOnDelete();
            $t->boolean('priority')->default(false);
            $t->timestamps();
            $t->unique(['public_account_id', 'mutual_id']);
            $t->index('mutual_id');
        });

        $s->create('social_mutes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('public_account_id')->constrained('public_accounts')->cascadeOnDelete();
            $t->string('mutual_id');
            $t->foreign('mutual_id')->references('id')->on('mutuals')->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['public_account_id', 'mutual_id']);
        });

        $s->create('social_saved_posts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('public_account_id')->constrained('public_accounts')->cascadeOnDelete();
            $t->foreignId('post_id')->constrained('social_posts')->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['public_account_id', 'post_id']);
        });

        $s->create('social_reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('public_account_id')->constrained('public_accounts')->cascadeOnDelete();
            $t->foreignId('post_id')->nullable()->constrained('social_posts')->cascadeOnDelete();
            $t->foreignId('comment_id')->nullable()->constrained('social_comments')->cascadeOnDelete();
            $t->string('reason', 40);
            $t->text('details')->nullable();
            $t->string('status', 20)->default('open');             // open | resolved | dismissed
            $t->unsignedBigInteger('handled_by')->nullable();
            $t->timestamp('handled_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'created_at']);
            $t->unique(['public_account_id', 'post_id', 'comment_id']);
        });

        $s->create('social_contact_requests', function (Blueprint $t) {
            $t->id();
            $t->string('mutual_id');
            $t->foreign('mutual_id')->references('id')->on('mutuals')->cascadeOnDelete();
            $t->foreignId('public_account_id')->nullable()->constrained('public_accounts')->nullOnDelete();
            $t->string('name', 180);
            $t->string('email', 180)->nullable();
            $t->string('phone', 40)->nullable();
            $t->string('subject', 40)->default('information');    // information | adhesion | partenariat | autre
            $t->text('message');
            $t->string('status', 20)->default('new');             // new | read | closed
            $t->timestamps();
            $t->index(['mutual_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        $s = Schema::connection('central');
        foreach (['social_contact_requests', 'social_reports', 'social_saved_posts', 'social_mutes',
                  'social_follows', 'social_comments', 'social_reactions', 'social_post_media',
                  'social_posts', 'mutual_profiles'] as $table) {
            $s->dropIfExists($table);
        }
        $s->table('public_accounts', function (Blueprint $t) {
            $t->dropIndex(['email']);
            $t->dropColumn(['avatar_path', 'bio', 'city', 'interests', 'profile_visibility', 'is_demo']);
        });
    }
};
