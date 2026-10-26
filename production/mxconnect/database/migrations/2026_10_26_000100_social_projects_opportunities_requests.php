<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Réseau social (base CENTRALE) — migration additive « projets, opportunités, demandes » :
 *  - projets publiés par une page (mutuelle OU page créée par un membre), avec résultats
 *    chiffrés DÉCLARÉS par l'organisation et, éventuellement, VÉRIFIÉS par l'administration du réseau ;
 *  - opportunités : emplois, stages, bénévolat, consultances, appels d'offres, appels à partenaires… ;
 *  - demandes reçues par une page : « Je m'engage », « Soutenir ou devenir partenaire », candidatures.
 * Aucune donnée médicale, aucun paiement : le soutien financier se règle hors plateforme.
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        $s = Schema::connection('central');

        if (! $s->hasTable('social_projects')) {
            $s->create('social_projects', function (Blueprint $t) {
                $t->id();
                $t->string('mutual_id');
                $t->foreign('mutual_id')->references('id')->on('mutuals')->cascadeOnDelete();
                $t->string('slug', 140)->unique();
                $t->string('title', 180);
                $t->string('summary', 300);
                $t->text('description')->nullable();
                $t->text('objectives')->nullable();
                $t->string('zone', 255)->nullable();                // zone d'intervention (texte libre)
                $t->string('region', 120)->nullable();
                $t->string('city', 120)->nullable();
                $t->string('phase', 20)->default('ongoing');        // planned | ongoing | completed
                $t->date('starts_on')->nullable();
                $t->date('ends_on')->nullable();
                $t->boolean('seeks_volunteers')->default(false);
                $t->text('volunteer_needs')->nullable();
                $t->boolean('seeks_partners')->default(false);
                $t->text('partner_needs')->nullable();
                $t->text('partners')->nullable();                   // partenaires déclarés (texte libre)
                $t->string('cover_path')->nullable();
                $t->string('status', 20)->default('published');     // published | draft | hidden (modération)
                $t->foreignId('created_by_account_id')->nullable()->constrained('public_accounts')->nullOnDelete();
                $t->unsignedBigInteger('tenant_user_id')->nullable();
                $t->boolean('is_demo')->default(false);
                $t->timestamp('published_at')->nullable();
                $t->timestamps();
                $t->softDeletes();
                $t->index(['status', 'phase']);
                $t->index('mutual_id');
            });
        }

        if (! $s->hasTable('social_project_indicators')) {
            $s->create('social_project_indicators', function (Blueprint $t) {
                $t->id();
                $t->foreignId('project_id')->constrained('social_projects')->cascadeOnDelete();
                $t->string('label', 160);
                $t->decimal('value', 16, 2);
                $t->decimal('target', 16, 2)->nullable();
                $t->string('unit', 40)->nullable();
                $t->string('period', 60)->nullable();
                $t->string('source_note', 255)->nullable();         // comment le chiffre a été obtenu
                $t->timestamp('verified_at')->nullable();
                $t->foreignId('verified_by')->nullable()->constrained('network_users')->nullOnDelete();
                $t->string('verification_note', 255)->nullable();
                $t->unsignedSmallInteger('position')->default(0);
                $t->timestamps();
            });
        }

        if (! $s->hasTable('social_project_updates')) {
            $s->create('social_project_updates', function (Blueprint $t) {
                $t->id();
                $t->foreignId('project_id')->constrained('social_projects')->cascadeOnDelete();
                $t->string('title', 180);
                $t->text('body');
                $t->foreignId('created_by_account_id')->nullable()->constrained('public_accounts')->nullOnDelete();
                $t->unsignedBigInteger('tenant_user_id')->nullable();
                $t->string('author_label', 180)->nullable();
                $t->timestamps();
                $t->index('project_id');
            });
        }

        if (! $s->hasTable('social_opportunities')) {
            $s->create('social_opportunities', function (Blueprint $t) {
                $t->id();
                $t->string('mutual_id');
                $t->foreign('mutual_id')->references('id')->on('mutuals')->cascadeOnDelete();
                $t->foreignId('project_id')->nullable()->constrained('social_projects')->nullOnDelete();
                $t->string('slug', 140)->unique();
                $t->string('category', 30);                         // emploi | stage | benevolat | consultance | appel_offres | …
                $t->string('title', 180);
                $t->text('description');
                $t->text('requirements')->nullable();
                $t->string('contract_type', 30)->nullable();        // cdi | cdd | stage | freelance | benevolat | autre
                $t->string('workplace', 20)->nullable();            // site | remote | hybrid
                $t->string('region', 120)->nullable();
                $t->string('city', 120)->nullable();
                $t->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
                $t->string('remuneration', 120)->nullable();        // texte libre, jamais converti
                $t->unsignedSmallInteger('positions')->nullable();
                $t->date('deadline_on')->nullable();
                $t->string('apply_mode', 20)->default('platform');  // platform | external
                $t->string('apply_url', 255)->nullable();
                $t->string('apply_instructions', 500)->nullable();
                $t->string('availability', 20)->default('open');    // open | filled | closed
                $t->string('status', 20)->default('published');     // published | draft | hidden (modération)
                $t->unsignedInteger('requests_count')->default(0);
                $t->foreignId('created_by_account_id')->nullable()->constrained('public_accounts')->nullOnDelete();
                $t->unsignedBigInteger('tenant_user_id')->nullable();
                $t->boolean('is_demo')->default(false);
                $t->timestamp('published_at')->nullable();
                $t->timestamps();
                $t->softDeletes();
                $t->index(['status', 'availability', 'deadline_on']);
                $t->index(['category', 'region']);
                $t->index('mutual_id');
            });
        }

        if (! $s->hasTable('social_requests')) {
            $s->create('social_requests', function (Blueprint $t) {
                $t->id();
                $t->string('reference', 20)->unique();
                $t->string('kind', 20);                             // engagement | support | application
                $t->string('mutual_id');
                $t->foreign('mutual_id')->references('id')->on('mutuals')->cascadeOnDelete();
                $t->foreignId('public_account_id')->constrained('public_accounts')->cascadeOnDelete();
                $t->foreignId('project_id')->nullable()->constrained('social_projects')->nullOnDelete();
                $t->foreignId('opportunity_id')->nullable()->constrained('social_opportunities')->nullOnDelete();
                $t->string('subtype', 40)->nullable();
                $t->string('organisation_name', 180)->nullable();   // « au nom de » (soutien d'une structure)
                $t->string('availability', 180)->nullable();        // disponibilités (engagement)
                $t->text('message');
                $t->string('contact_name', 180);
                $t->string('contact_phone', 40)->nullable();
                $t->string('contact_email', 180)->nullable();
                $t->string('cv_path')->nullable();                  // disque PRIVÉ
                $t->string('cv_name', 180)->nullable();
                $t->string('status', 20)->default('submitted');     // submitted | in_review | accepted | rejected | withdrawn | closed
                $t->timestamp('consent_at')->nullable();
                $t->timestamp('decided_at')->nullable();
                $t->timestamps();
                $t->index(['mutual_id', 'status']);
                $t->index(['public_account_id', 'status']);
            });
        }

        if (! $s->hasTable('social_request_events')) {
            $s->create('social_request_events', function (Blueprint $t) {
                $t->id();
                $t->foreignId('social_request_id')->constrained('social_requests')->cascadeOnDelete();
                $t->string('actor', 20);                            // requester | organisation
                $t->string('actor_label', 180)->nullable();
                $t->string('from_status', 20)->nullable();
                $t->string('to_status', 20);
                $t->text('note')->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }

        if (! $s->hasColumn('social_posts', 'project_id')) {
            $s->table('social_posts', function (Blueprint $t) {
                $t->foreignId('project_id')->nullable()->constrained('social_projects')->nullOnDelete();
                $t->foreignId('opportunity_id')->nullable()->constrained('social_opportunities')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        $s = Schema::connection('central');
        if ($s->hasColumn('social_posts', 'project_id')) {
            $s->table('social_posts', function (Blueprint $t) {
                $t->dropConstrainedForeignId('project_id');
                $t->dropConstrainedForeignId('opportunity_id');
            });
        }
        foreach (['social_request_events', 'social_requests', 'social_opportunities', 'social_project_updates', 'social_project_indicators', 'social_projects'] as $table) {
            $s->dropIfExists($table);
        }
    }
};
