<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Réseau social (base CENTRALE) — migration additive :
 *  - gestionnaires des pages (publient au nom de la page, y compris pour les mutuelles) ;
 *  - abonnement entre membres + photo de couverture du profil ;
 *  - signalement des profils et des pages, suspension d'un compte ;
 *  - comptes annonceurs et publications sponsorisées ;
 *  - réglages du réseau (mode allégé, tarifs de sponsorisation).
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        $s = Schema::connection('central');

        if (! $s->hasTable('social_page_admins')) {
            $s->create('social_page_admins', function (Blueprint $t) {
                $t->id();
                $t->string('mutual_id');
                $t->foreign('mutual_id')->references('id')->on('mutuals')->cascadeOnDelete();
                $t->foreignId('public_account_id')->constrained('public_accounts')->cascadeOnDelete();
                $t->string('role', 20)->default('editor');        // admin | editor
                $t->foreignId('added_by_account_id')->nullable()->constrained('public_accounts')->nullOnDelete();
                $t->timestamps();
                $t->unique(['mutual_id', 'public_account_id']);
                $t->index('public_account_id');
            });
            // Les propriétaires existants deviennent administrateurs de leur page.
            DB::connection('central')->statement(
                "INSERT INTO social_page_admins (mutual_id, public_account_id, role, created_at, updated_at)
                 SELECT mutual_id, owner_account_id, 'admin', NOW(), NOW() FROM mutual_profiles WHERE owner_account_id IS NOT NULL");
        }

        if (! $s->hasTable('social_user_follows')) {
            $s->create('social_user_follows', function (Blueprint $t) {
                $t->id();
                $t->foreignId('follower_id')->constrained('public_accounts')->cascadeOnDelete();
                $t->foreignId('followed_id')->constrained('public_accounts')->cascadeOnDelete();
                $t->timestamps();
                $t->unique(['follower_id', 'followed_id']);
                $t->index('followed_id');
            });
        }

        if (! $s->hasColumn('public_accounts', 'cover_path')) {
            $s->table('public_accounts', function (Blueprint $t) {
                $t->string('cover_path')->nullable()->after('avatar_path');
                $t->timestamp('suspended_at')->nullable();
                $t->string('suspension_reason', 255)->nullable();
            });
        }

        if (! $s->hasColumn('social_reports', 'reported_account_id')) {
            $s->table('social_reports', function (Blueprint $t) {
                $t->foreignId('reported_account_id')->nullable()->constrained('public_accounts')->cascadeOnDelete();
                $t->string('reported_mutual_id')->nullable();
                $t->foreign('reported_mutual_id')->references('id')->on('mutuals')->cascadeOnDelete();
            });
        }

        if (! $s->hasTable('social_advertisers')) {
            $s->create('social_advertisers', function (Blueprint $t) {
                $t->id();
                $t->foreignId('public_account_id')->unique()->constrained('public_accounts')->cascadeOnDelete();
                $t->string('display_name', 160);
                $t->string('organisation', 160)->nullable();
                $t->string('phone', 40);
                $t->string('email', 180)->nullable();
                $t->string('city', 120)->nullable();
                $t->string('status', 20)->default('active');         // active | suspended
                $t->timestamps();
            });
        }

        if (! $s->hasTable('social_sponsorships')) {
            $s->create('social_sponsorships', function (Blueprint $t) {
                $t->id();
                $t->foreignId('advertiser_id')->constrained('social_advertisers')->cascadeOnDelete();
                $t->foreignId('post_id')->constrained('social_posts')->cascadeOnDelete();
                $t->foreignId('currency_id')->constrained('currencies');
                $t->unsignedBigInteger('budget_minor');
                $t->unsignedBigInteger('cpm_minor');                  // prix pour 1 000 affichages (au moment de la demande)
                $t->unsignedInteger('max_impressions');
                $t->unsignedInteger('impressions')->default(0);
                $t->unsignedInteger('clicks')->default(0);
                $t->string('target_city', 120)->nullable();
                $t->string('target_interest', 40)->nullable();
                $t->string('cta_label', 40)->nullable();
                $t->date('starts_on');
                $t->date('ends_on');
                $t->string('status', 20)->default('pending_payment'); // pending_payment|active|paused|rejected|completed|cancelled
                $t->string('payment_reference', 120)->nullable();     // référence du paiement saisie par l'annonceur
                $t->unsignedBigInteger('validated_by')->nullable();   // utilisateur du back-office réseau
                $t->timestamp('validated_at')->nullable();
                $t->string('admin_note', 255)->nullable();
                $t->timestamps();
                $t->index(['status', 'starts_on', 'ends_on']);
            });
        }

        if (! $s->hasTable('social_settings')) {
            $s->create('social_settings', function (Blueprint $t) {
                $t->string('key', 60)->primary();
                $t->text('value')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        $s = Schema::connection('central');
        foreach (['social_settings', 'social_sponsorships', 'social_advertisers', 'social_user_follows', 'social_page_admins'] as $table) {
            $s->dropIfExists($table);
        }
        if ($s->hasColumn('social_reports', 'reported_account_id')) {
            $s->table('social_reports', function (Blueprint $t) {
                $t->dropConstrainedForeignId('reported_account_id');
                $t->dropForeign(['reported_mutual_id']);
                $t->dropColumn('reported_mutual_id');
            });
        }
        if ($s->hasColumn('public_accounts', 'cover_path')) {
            $s->table('public_accounts', fn (Blueprint $t) => $t->dropColumn(['cover_path', 'suspended_at', 'suspension_reason']));
        }
    }
};
