<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Base CENTRALE — application Android bêta (2026-11-09), migration additive.
 *  - legal_documents : conditions d'utilisation, politique de confidentialité, règles
 *    communautaires, consentements spécifiques ; VERSIONNÉS (une version publiée n'est
 *    jamais modifiée : on en publie une nouvelle) ;
 *  - legal_acceptances : historique des acceptations / prises de connaissance / consentements
 *    (rien n'est écrasé) ;
 *  - beta_feedback : retours des testeurs de l'application ;
 *  - public_accounts.phone devient facultatif (inscription par e-mail possible, maquette 4) ;
 *    l'unicité est conservée pour les numéros saisis.
 * Les textes de départ sont insérés par App\Services\Social\LegalService::seedDefaults().
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        $s = Schema::connection('central');

        if (! $s->hasTable('legal_documents')) {
            $s->create('legal_documents', function (Blueprint $t) {
                $t->id();
                $t->string('type', 40);                       // terms | privacy | community | consent_*
                $t->string('version', 20);                    // ex. 1.0
                $t->string('title', 180);
                $t->string('summary', 500)->nullable();
                $t->longText('body');
                $t->string('action', 20)->default('accept');  // accept | acknowledge | consent
                $t->boolean('requires_reacceptance')->default(false);
                $t->boolean('is_template')->default(false);   // « MODÈLE — À FAIRE VALIDER »
                $t->string('status', 20)->default('draft');   // draft | published | archived
                $t->timestamp('published_at')->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamps();
                $t->unique(['type', 'version']);
                $t->index(['type', 'status', 'published_at']);
            });
        }
        if (! $s->hasTable('legal_acceptances')) {
            $s->create('legal_acceptances', function (Blueprint $t) {
                $t->id();
                $t->foreignId('public_account_id')->constrained('public_accounts')->cascadeOnDelete();
                $t->foreignId('legal_document_id')->constrained('legal_documents');
                $t->string('type', 40);
                $t->string('version', 20);
                $t->string('action', 30);                     // accepted | acknowledged | consent_given | consent_withdrawn
                $t->string('channel', 20)->default('web');    // web | android
                $t->string('ip', 45)->nullable();
                $t->string('user_agent', 255)->nullable();
                $t->timestamp('created_at')->useCurrent();
                $t->index(['public_account_id', 'type']);
            });
        }
        if (! $s->hasTable('beta_feedback')) {
            $s->create('beta_feedback', function (Blueprint $t) {
                $t->id();
                $t->foreignId('public_account_id')->nullable()->constrained('public_accounts')->nullOnDelete();
                $t->string('category', 30);                   // bug | idea | question | other
                $t->text('message');
                $t->string('screen', 120)->nullable();        // page concernée
                $t->string('app_version', 30)->nullable();
                $t->string('device', 160)->nullable();        // saisi par le testeur ou navigateur
                $t->string('contact', 180)->nullable();
                $t->string('status', 20)->default('new');     // new | read | done
                $t->timestamps();
            });
        }
        if ($s->hasTable('public_accounts')) {
            $s->table('public_accounts', fn (Blueprint $t) => $t->string('phone', 20)->nullable()->change());
        }

        try {
            app(\App\Services\Social\LegalService::class)->seedDefaults();
        } catch (\Throwable $e) {
            report($e);   // les textes peuvent être créés plus tard depuis l'espace super-admin
        }
    }

    public function down(): void
    {
        $s = Schema::connection('central');
        $s->dropIfExists('beta_feedback');
        $s->dropIfExists('legal_acceptances');
        $s->dropIfExists('legal_documents');
    }
};
