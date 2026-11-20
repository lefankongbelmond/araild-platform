<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Base de CHAQUE mutuelle — portail prestataire V2, phase 5 « Facturation et paiements »
 * (2026-11-26). ADDITIVE : aucune facture existante n'est modifiée.
 *  - provider_invoice_lines : une ligne par prestation facturée (bordereau), décidée une à une
 *    par la mutuelle (acceptée / rejetée avec motif) ; une ligne rejetée peut être renvoyée
 *    dans un nouveau bordereau ;
 *  - invoice_events : historique d'une facture (envoi, contrôle, rejet, paiement), visible du
 *    prestataire sans le nom des agents ;
 *  - provider_invoices : nature (claim = une prise en charge, batch = bordereau), référence
 *    BOR-AAAA-NNNNN, montant demandé, contrôle (qui, quand) et paiement CONFIRMÉ (référence
 *    du virement ou de la transaction, date, mode).
 * Les anciennes factures, sans ligne, gardent leur fonctionnement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_invoices', function (Blueprint $t) {
            if (! Schema::hasColumn('provider_invoices', 'kind')) {
                $t->string('kind', 10)->default('claim')->after('care_claim_id');
            }
            if (! Schema::hasColumn('provider_invoices', 'reference')) {
                $t->string('reference', 20)->nullable()->after('kind');
            }
            if (! Schema::hasColumn('provider_invoices', 'billed_minor')) {
                $t->bigInteger('billed_minor')->nullable()->after('amount_minor');
            }
            if (! Schema::hasColumn('provider_invoices', 'checked_by')) {
                $t->unsignedBigInteger('checked_by')->nullable();
                $t->timestamp('checked_at')->nullable();
            }
            if (! Schema::hasColumn('provider_invoices', 'payment_reference')) {
                $t->string('payment_reference', 60)->nullable();
                $t->date('paid_on')->nullable();
                $t->string('payment_mode', 20)->nullable();
            }
        });

        if (! Schema::hasTable('provider_invoice_lines')) {
            Schema::create('provider_invoice_lines', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('provider_invoice_id');
                $t->unsignedBigInteger('prestation_id');
                $t->unsignedBigInteger('care_claim_id')->nullable();
                $t->date('care_date')->nullable();
                $t->bigInteger('amount_minor');
                $t->string('status', 12)->default('pending');   // pending, accepted, rejected
                $t->string('decision_note', 500)->nullable();
                $t->unsignedBigInteger('decided_by')->nullable();
                $t->timestamp('decided_at')->nullable();
                $t->timestamps();
                $t->index('provider_invoice_id');
                $t->index(['prestation_id', 'status']);
            });
        }

        if (! Schema::hasTable('invoice_events')) {
            Schema::create('invoice_events', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('provider_invoice_id');
                $t->string('event', 20);       // sent, document, checked, rejected, paid
                $t->unsignedBigInteger('user_id')->nullable();
                $t->bigInteger('amount_minor')->nullable();
                $t->string('note', 500)->nullable();
                $t->timestamp('created_at')->nullable();
                $t->index('provider_invoice_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_events');
        Schema::dropIfExists('provider_invoice_lines');
        foreach (['payment_mode', 'paid_on', 'payment_reference', 'checked_at', 'checked_by', 'billed_minor', 'reference', 'kind'] as $c) {
            if (Schema::hasColumn('provider_invoices', $c)) {
                Schema::table('provider_invoices', fn (Blueprint $t) => $t->dropColumn($c));
            }
        }
    }
};
