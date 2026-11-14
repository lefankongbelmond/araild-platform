<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CENTRALE — Portail prestataire V2, phase 2 (2026-11-16) : espace prestataire central.
 * Décision du fondateur : UN compte par personne, rôles DANS l'établissement, plusieurs
 * mutuelles. Les données de soins restent dans la base de chaque mutuelle ; ces tables ne
 * contiennent ni adhérent ni donnée médicale.
 *
 *  - care_providers (complété) : fiche de l'établissement (agrément, spécialités, adresse,
 *    géolocalisation, contacts, horaires, coordonnées bancaires CHIFFRÉES, état d'inscription) ;
 *  - provider_accounts : comptes des personnes (garde « provider », double authentification) ;
 *  - provider_memberships : rattachement d'une personne à un établissement, avec son rôle et
 *    ses droits ajoutés / retirés (gestion fine) ;
 *  - provider_documents : agréments, conventions et pièces (disque privé, PDF contrôlé) ;
 *  - provider_affiliations : demandes d'affiliation et index des conventions par mutuelle
 *    (la convention elle-même reste dans la base de la mutuelle) ;
 *  - provider_handoffs : jetons à usage unique (60 s) pour ouvrir le portail d'une mutuelle ;
 *  - provider_password_reset_tokens : « mot de passe oublié ».
 * Migration additive : aucune donnée supprimée, aucune colonne existante modifiée.
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        $s = Schema::connection('central');

        if (! $s->hasColumn('care_providers', 'registration_status')) {
            $s->table('care_providers', function (Blueprint $t) {
                // approved = établissement connu (registre des mutuelles) ; pending = inscrit en ligne,
                // en attente d'une première affiliation acceptée par une mutuelle.
                $t->string('registration_status', 12)->default('approved')->after('active');
                $t->string('approval_number', 60)->nullable()->after('registration_status');
                $t->date('approval_expires_on')->nullable()->after('approval_number');
                $t->json('specialties')->nullable();
                $t->string('address', 255)->nullable();
                $t->decimal('latitude', 10, 7)->nullable();
                $t->decimal('longitude', 10, 7)->nullable();
                $t->string('phone2', 30)->nullable();
                $t->string('email', 180)->nullable();
                $t->json('opening_hours')->nullable();
                $t->text('bank_details')->nullable();          // JSON chiffré (APP_KEY)
                $t->string('manager_name', 120)->nullable();
            });
        }

        if (! $s->hasTable('provider_accounts')) {
            $s->create('provider_accounts', function (Blueprint $t) {
                $t->id();
                $t->string('first_name', 120);
                $t->string('last_name', 120);
                $t->string('email', 180)->unique();
                $t->string('phone', 30)->nullable();
                $t->string('password');
                $t->text('mfa_secret')->nullable();            // chiffré
                $t->boolean('mfa_enabled')->default(false);
                $t->boolean('must_change_password')->default(false);
                $t->boolean('active')->default(true);
                $t->timestamp('last_login_at')->nullable();
                $t->rememberToken();
                $t->timestamps();
            });
        }

        if (! $s->hasTable('provider_memberships')) {
            $s->create('provider_memberships', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('provider_account_id');
                $t->unsignedBigInteger('care_provider_id');
                $t->string('role', 24);
                $t->string('job_title', 120)->nullable();
                $t->json('permissions')->nullable();           // {"grant":[…],"deny":[…]}
                $t->boolean('active')->default(true);
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamps();
                $t->unique(['provider_account_id', 'care_provider_id']);
                $t->index(['care_provider_id', 'active']);
            });
        }

        if (! $s->hasTable('provider_documents')) {
            $s->create('provider_documents', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('care_provider_id');
                $t->string('kind', 24);
                $t->string('title', 160);
                $t->string('reference', 80)->nullable();
                $t->date('issued_on')->nullable();
                $t->date('expires_on')->nullable();
                $t->string('mutual_id', 64)->nullable();       // convention liée à une mutuelle
                $t->string('path', 255);
                $t->string('original_name', 180)->nullable();
                $t->unsignedInteger('size')->default(0);
                $t->unsignedBigInteger('uploaded_by')->nullable();
                $t->timestamps();
                $t->softDeletes();                             // historique conservé
                $t->index(['care_provider_id', 'kind']);
            });
        }

        if (! $s->hasTable('provider_affiliations')) {
            $s->create('provider_affiliations', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('care_provider_id');
                $t->string('mutual_id', 64);
                // requested, approved, suspended, rejected, withdrawn
                $t->string('status', 12)->default('requested');
                $t->text('message')->nullable();
                $t->unsignedBigInteger('requested_by')->nullable();
                $t->text('decision_note')->nullable();
                $t->timestamp('decided_at')->nullable();
                $t->json('history')->nullable();
                $t->timestamps();
                $t->unique(['care_provider_id', 'mutual_id']);
                $t->index(['mutual_id', 'status']);
            });
        }

        if (! $s->hasTable('provider_handoffs')) {
            $s->create('provider_handoffs', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('provider_account_id');
                $t->unsignedBigInteger('care_provider_id');
                $t->string('mutual_id', 64);
                $t->char('token_hash', 64)->unique();
                $t->string('target', 20)->default('verify');
                $t->timestamp('expires_at');
                $t->timestamp('used_at')->nullable();
                $t->string('ip', 45)->nullable();
                $t->timestamp('created_at')->nullable();
                $t->index('expires_at');
            });
        }

        if (! $s->hasTable('provider_password_reset_tokens')) {
            $s->create('provider_password_reset_tokens', function (Blueprint $t) {
                $t->string('email')->primary();
                $t->string('token');
                $t->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        $s = Schema::connection('central');
        foreach (['provider_password_reset_tokens', 'provider_handoffs', 'provider_affiliations', 'provider_documents', 'provider_memberships', 'provider_accounts'] as $tbl) {
            $s->dropIfExists($tbl);
        }
        if ($s->hasColumn('care_providers', 'registration_status')) {
            $s->table('care_providers', fn (Blueprint $t) => $t->dropColumn([
                'registration_status', 'approval_number', 'approval_expires_on', 'specialties', 'address', 'latitude', 'longitude',
                'phone2', 'email', 'opening_hours', 'bank_details', 'manager_name',
            ]));
        }
    }
};
