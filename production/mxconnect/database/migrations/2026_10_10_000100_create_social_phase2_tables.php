<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Réseau social — livraison 2 (base CENTRALE) : demandes d'adhésion en ligne,
 * messagerie membre ↔ mutuelle, notifications. Additif et réversible.
 *
 * Les pièces jointes des dossiers d'adhésion ne sont JAMAIS dans un dossier
 * public : disque « social_private » (storage/app/private-social), servi
 * uniquement par un contrôleur qui vérifie les droits.
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        $s = Schema::connection('central');

        $s->create('adhesion_requests', function (Blueprint $t) {
            $t->id();
            $t->string('reference', 30)->unique();
            $t->string('mutual_id');
            $t->foreign('mutual_id')->references('id')->on('mutuals')->cascadeOnDelete();
            $t->foreignId('public_account_id')->constrained('public_accounts')->cascadeOnDelete();
            // submitted | in_review | info_requested | accepted | rejected | withdrawn
            $t->string('status', 20)->default('submitted');
            $t->string('first_name', 120);
            $t->string('last_name', 120);
            $t->date('birth_date');
            $t->char('sex', 1);
            $t->string('phone', 20);
            $t->string('email', 180)->nullable();
            $t->string('id_document_number', 60)->nullable();
            $t->string('city', 120)->nullable();
            $t->string('address', 255)->nullable();
            $t->string('profession', 120)->nullable();
            $t->unsignedTinyInteger('household_size')->default(1);
            $t->json('beneficiaries')->nullable();          // [{name, relation, birth_date}]
            $t->string('formula', 180)->nullable();
            $t->text('message')->nullable();
            $t->timestamp('consent_at');
            $t->unsignedBigInteger('member_id')->nullable(); // fiche adhérent liée (base de la mutuelle)
            $t->string('member_code', 40)->nullable();
            $t->timestamp('decided_at')->nullable();
            $t->unsignedBigInteger('decided_by')->nullable();
            $t->timestamps();
            $t->index(['mutual_id', 'status', 'created_at']);
            $t->index(['public_account_id', 'created_at']);
        });

        $s->create('adhesion_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('adhesion_request_id')->constrained('adhesion_requests')->cascadeOnDelete();
            $t->string('kind', 30);                         // piece_identite | photo | justificatif | autre
            $t->string('path');
            $t->string('original_name', 190);
            $t->string('mime', 80);
            $t->unsignedInteger('size');
            $t->timestamps();
        });

        $s->create('adhesion_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('adhesion_request_id')->constrained('adhesion_requests')->cascadeOnDelete();
            $t->string('actor', 20);                        // applicant | mutual
            $t->string('actor_label', 180)->nullable();
            $t->string('from_status', 20)->nullable();
            $t->string('to_status', 20);
            $t->text('note')->nullable();
            $t->timestamp('created_at')->nullable();
        });

        $s->create('social_conversations', function (Blueprint $t) {
            $t->id();
            $t->string('mutual_id');
            $t->foreign('mutual_id')->references('id')->on('mutuals')->cascadeOnDelete();
            $t->foreignId('public_account_id')->constrained('public_accounts')->cascadeOnDelete();
            $t->foreignId('adhesion_request_id')->nullable()->constrained('adhesion_requests')->nullOnDelete();
            $t->string('subject', 180);
            $t->string('status', 20)->default('open');      // open | closed
            $t->unsignedInteger('account_unread')->default(0);
            $t->unsignedInteger('mutual_unread')->default(0);
            $t->timestamp('last_message_at')->nullable();
            $t->timestamps();
            $t->index(['public_account_id', 'last_message_at']);
            $t->index(['mutual_id', 'last_message_at']);
        });

        $s->create('social_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversation_id')->constrained('social_conversations')->cascadeOnDelete();
            $t->string('sender', 10);                       // account | mutual
            $t->unsignedBigInteger('tenant_user_id')->nullable();
            $t->text('body');
            $t->timestamps();
            $t->index(['conversation_id', 'id']);
        });

        $s->create('social_notifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('public_account_id')->constrained('public_accounts')->cascadeOnDelete();
            $t->string('type', 40);
            $t->string('title', 190);
            $t->string('body', 280)->nullable();
            $t->string('url', 300)->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
            $t->index(['public_account_id', 'read_at', 'id']);
        });
    }

    public function down(): void
    {
        $s = Schema::connection('central');
        foreach (['social_notifications', 'social_messages', 'social_conversations',
                  'adhesion_events', 'adhesion_documents', 'adhesion_requests'] as $table) {
            $s->dropIfExists($table);
        }
    }
};
