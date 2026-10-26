<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MX-CONNECT social network layer (CENTRAL, additive — Lot 1 "fondations").
 *
 * This sits NEXT TO the existing tenant-isolated mutual feed (app/Models/Tenant/FeedPost
 * & co.), it does not replace it: that feed stays a mutual-internal, members-only space
 * inside each tenant database. These tables hold the PUBLIC, cross-organization layer —
 * organization profiles, a public feed, projects/initiatives, and the "Je m'engage" /
 * "Soutenir ou devenir partenaire" engagement workflows — scoped to the central database
 * so they are visible network-wide, the same way `mutuals`, `public_accounts` and the
 * directory already are.
 *
 * `organizations.mutual_id` is a nullable, optional link to an existing tenant (`mutuals`):
 * a mutual MAY have a public organization profile, but an organization does not require a
 * mutual (associations, NGOs, care providers, partners, etc. are first-class here too).
 * No existing table is altered.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('central');

        $schema->create('organizations', function (Blueprint $t) {
            $t->id();
            $t->string('mutual_id')->nullable();
            $t->enum('type', ['mutual', 'ong', 'association', 'entreprise_sociale', 'institution_publique', 'autre'])->default('autre');
            $t->string('name', 180);
            $t->string('slug', 80)->unique();
            $t->string('tagline', 200)->nullable();
            $t->text('description')->nullable();
            $t->string('logo_path')->nullable();
            $t->string('cover_path')->nullable();
            $t->foreignId('country_id')->nullable()->constrained('countries');
            $t->string('region', 120)->nullable();
            $t->string('city', 120)->nullable();
            $t->string('email', 180)->nullable();
            $t->string('phone', 30)->nullable();
            $t->string('website')->nullable();
            $t->json('socials')->nullable();           // {facebook, linkedin, youtube, x, instagram}
            $t->json('domains')->nullable();            // intervention domain tags, e.g. ["Santé communautaire", "Protection sociale"]
            $t->json('stats')->nullable();               // free-form public counters the org curates, e.g. {"Adhérents": "12 560"}
            $t->enum('status', ['draft', 'published', 'suspended'])->default('draft');
            $t->timestamp('verified_at')->nullable();   // null = NOT verified; never display a badge otherwise
            $t->foreignId('verified_by')->nullable()->constrained('network_users')->nullOnDelete();
            $t->unsignedInteger('followers_count')->default(0);
            $t->timestamps();
            $t->softDeletes();

            $t->foreign('mutual_id')->references('id')->on('mutuals')->nullOnDelete();
            $t->unique('mutual_id');
            $t->index(['type', 'status']);
        });

        // Who may manage / post as an organization's public profile (distinct from a
        // mutual's internal back-office `users` — this is the social-profile editor list).
        $schema->create('organization_managers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $t->foreignId('public_account_id')->constrained('public_accounts')->cascadeOnDelete();
            $t->enum('role', ['owner', 'admin', 'editor'])->default('editor');
            $t->timestamps();
            $t->unique(['organization_id', 'public_account_id']);
        });

        $schema->create('organization_follows', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $t->foreignId('public_account_id')->constrained('public_accounts')->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['organization_id', 'public_account_id']);
        });

        $schema->create('hashtags', function (Blueprint $t) {
            $t->id();
            $t->string('tag', 100)->unique();   // normalized, lowercase, no leading #
            $t->unsignedInteger('uses_count')->default(0);
            $t->timestamps();
        });

        $schema->create('projects', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $t->string('title', 200);
            $t->string('slug', 220)->unique();
            $t->string('summary', 300)->nullable();
            $t->text('description')->nullable();
            $t->text('objectives')->nullable();
            $t->string('zone', 200)->nullable();
            $t->string('beneficiaries_summary', 255)->nullable();
            $t->string('cover_path')->nullable();
            $t->enum('status', ['planifie', 'en_cours', 'termine'])->default('planifie');
            $t->unsignedTinyInteger('progress_percent')->default(0);
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->json('partners')->nullable();
            $t->json('documents')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['organization_id', 'status']);
        });

        // Declared-vs-verified results (never invent figures — `is_verified` stays false
        // until a network operator checks the evidence behind it).
        $schema->create('project_indicators', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $t->string('label', 200);
            $t->string('value', 100);
            $t->string('unit', 40)->nullable();
            $t->boolean('is_verified')->default(false);
            $t->foreignId('verified_by')->nullable()->constrained('network_users')->nullOnDelete();
            $t->timestamp('verified_at')->nullable();
            $t->string('source_note', 255)->nullable();
            $t->timestamps();
            $t->index('project_id');
        });

        $schema->create('project_updates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $t->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $t->string('title', 200);
            $t->text('body');
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index('project_id');
        });

        $schema->create('posts', function (Blueprint $t) {
            $t->id();
            // Author is EITHER an organization OR an individual public account — exactly
            // one is set (enforced in the service layer, Lot 2).
            $t->foreignId('organization_id')->nullable()->constrained('organizations')->cascadeOnDelete();
            $t->foreignId('public_account_id')->nullable()->constrained('public_accounts')->cascadeOnDelete();
            $t->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $t->enum('type', ['text', 'photo', 'video', 'event', 'poll', 'opportunity', 'result'])->default('text');
            $t->text('body')->nullable();
            $t->json('media')->nullable();              // [{path, type}]
            $t->timestamp('event_starts_at')->nullable();
            $t->string('event_location', 255)->nullable();
            $t->boolean('event_online')->default(false);
            $t->json('poll_options')->nullable();
            $t->timestamp('poll_closes_at')->nullable();
            $t->string('opportunity_kind', 60)->nullable();   // appel_partenariat, besoin_equipement, recherche_competences...
            $t->enum('visibility', ['public', 'followers'])->default('public');
            $t->enum('status', ['draft', 'published', 'removed'])->default('published');
            $t->timestamp('pinned_at')->nullable();
            $t->timestamp('published_at')->nullable();
            $t->unsignedInteger('likes_count')->default(0);
            $t->unsignedInteger('comments_count')->default(0);
            $t->unsignedInteger('shares_count')->default(0);
            $t->timestamps();
            $t->softDeletes();
            $t->index(['status', 'visibility', 'published_at']);
            $t->index('organization_id');
            $t->index('public_account_id');
        });

        $schema->create('post_hashtag', function (Blueprint $t) {
            $t->id();
            $t->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $t->foreignId('hashtag_id')->constrained('hashtags')->cascadeOnDelete();
            $t->unique(['post_id', 'hashtag_id']);
        });

        $schema->create('post_likes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $t->foreignId('public_account_id')->constrained('public_accounts')->cascadeOnDelete();
            $t->string('reaction', 20)->default('like');
            $t->timestamps();
            $t->unique(['post_id', 'public_account_id']);
        });

        $schema->create('post_comments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $t->foreignId('public_account_id')->constrained('public_accounts')->cascadeOnDelete();
            $t->foreignId('parent_id')->nullable()->constrained('post_comments')->cascadeOnDelete();
            $t->text('body');
            $t->enum('status', ['active', 'removed'])->default('active');
            $t->timestamps();
            $t->softDeletes();
            $t->index('post_id');
        });

        // Action A — "Je m'engage".
        $schema->create('engagement_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $t->foreignId('post_id')->nullable()->constrained('posts')->cascadeOnDelete();
            $t->foreignId('project_id')->nullable()->constrained('projects')->cascadeOnDelete();
            $t->foreignId('public_account_id')->constrained('public_accounts')->cascadeOnDelete();
            $t->enum('contribution_type', ['benevolat', 'participation', 'competence', 'accompagnement', 'autre']);
            $t->text('message')->nullable();
            $t->enum('status', ['envoyee', 'en_cours', 'acceptee', 'refusee', 'cloturee'])->default('envoyee');
            $t->foreignId('responded_by_account_id')->nullable()->constrained('public_accounts')->nullOnDelete();
            $t->timestamp('responded_at')->nullable();
            $t->text('response_note')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['organization_id', 'status']);
            $t->index('public_account_id');
        });

        // Action C — "Soutenir ou devenir partenaire".
        $schema->create('support_offers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $t->foreignId('post_id')->nullable()->constrained('posts')->cascadeOnDelete();
            $t->foreignId('project_id')->nullable()->constrained('projects')->cascadeOnDelete();
            $t->foreignId('public_account_id')->constrained('public_accounts')->cascadeOnDelete();
            $t->enum('support_type', ['financier', 'materiel', 'expertise', 'accompagnement', 'partenariat_operationnel', 'autre']);
            $t->text('message')->nullable();
            $t->json('contact_info')->nullable();
            $t->enum('status', ['envoyee', 'en_cours', 'acceptee', 'refusee', 'cloturee'])->default('envoyee');
            $t->foreignId('responded_by_account_id')->nullable()->constrained('public_accounts')->nullOnDelete();
            $t->timestamp('responded_at')->nullable();
            $t->text('response_note')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['organization_id', 'status']);
            $t->index('public_account_id');
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('central');
        $schema->dropIfExists('support_offers');
        $schema->dropIfExists('engagement_requests');
        $schema->dropIfExists('post_comments');
        $schema->dropIfExists('post_likes');
        $schema->dropIfExists('post_hashtag');
        $schema->dropIfExists('posts');
        $schema->dropIfExists('project_updates');
        $schema->dropIfExists('project_indicators');
        $schema->dropIfExists('projects');
        $schema->dropIfExists('hashtags');
        $schema->dropIfExists('organization_follows');
        $schema->dropIfExists('organization_managers');
        $schema->dropIfExists('organizations');
    }
};
