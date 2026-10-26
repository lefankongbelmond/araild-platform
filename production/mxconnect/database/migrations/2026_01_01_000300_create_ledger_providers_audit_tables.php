<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment ledger + shared care-provider registry + network audit (CENTRAL).
 *
 * The ledger is APPEND-ONLY: no deletes, no destructive updates on financial
 * fields. Corrections are counter-entries linked via linked_transaction_id.
 * Money never transits MX-CONNECT — this table only observes and reconciles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('payment_transactions', function (Blueprint $t) {
            $t->id();
            $t->uuid('reference')->unique();             // idempotency key
            $t->string('mutual_id');
            $t->unsignedBigInteger('public_account_id')->nullable();
            $t->unsignedBigInteger('member_id')->nullable();   // id inside tenant DB
            $t->unsignedBigInteger('schedule_id')->nullable(); // contribution schedule row inside tenant DB
            $t->foreignId('payment_provider_id')->nullable()->constrained('payment_providers');
            $t->foreignId('currency_id')->constrained('currencies');
            $t->enum('type', ['membership_fee', 'contribution']);
            $t->bigInteger('amount_minor');              // amount in minor units of the currency
            $t->string('provider_ref', 120)->nullable(); // reference returned by aggregator
            $t->enum('status', ['initiated', 'pending', 'confirmed', 'failed', 'expired'])->default('initiated');
            $t->enum('reconciliation_status', ['unreconciled', 'reconciled', 'discrepancy'])->default('unreconciled');
            $t->timestamp('webhook_received_at')->nullable();
            $t->json('webhook_payload')->nullable();
            $t->unsignedBigInteger('linked_transaction_id')->nullable(); // counter-entry
            $t->timestamps();
            $t->foreign('mutual_id')->references('id')->on('mutuals')->cascadeOnDelete();
            $t->index(['mutual_id', 'status']);
            $t->index('reconciliation_status');
        });

        // Shared registry of care providers (an entity may serve several mutuals).
        Schema::connection('central')->create('providers', function (Blueprint $t) {
            $t->id();
            $t->string('name', 180);
            $t->enum('type', ['hospital', 'clinic', 'health_center', 'pharmacy', 'laboratory']);
            $t->foreignId('country_id')->constrained('countries');
            $t->string('region', 120)->nullable();
            $t->string('city', 120)->nullable();
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->string('contact', 120)->nullable();
            $t->enum('status', ['active', 'inactive'])->default('active');
            $t->timestamps();
        });

        Schema::connection('central')->create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('network_user_id')->nullable();
            $t->string('action', 60);
            $t->string('object_type', 100)->nullable();
            $t->unsignedBigInteger('object_id')->nullable();
            $t->json('before')->nullable();
            $t->json('after')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamp('occurred_at', 3)->useCurrent();
            // append-only: intentionally NO updated_at / deleted_at
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('audit_logs');
        Schema::connection('central')->dropIfExists('providers');
        Schema::connection('central')->dropIfExists('payment_transactions');
    }
};
