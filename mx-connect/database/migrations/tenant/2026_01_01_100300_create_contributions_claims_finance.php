<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TENANT schema — contributions, care claims chain, treasury, SYSCOHADA accounting,
 * anti-fraud alerts, and transverse tables (documents, audit).
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- Contributions ---
        Schema::create('contribution_schedules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $t->string('period', 20);                       // "2026-Q3" / "2026-07"
            $t->smallInteger('year');
            $t->bigInteger('due_minor');
            $t->date('due_date');
            $t->enum('status', ['to_pay', 'paid', 'overdue'])->default('to_pay');
            $t->timestamps();
            $t->index(['status', 'due_date']);
        });

        Schema::create('contribution_payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('schedule_id')->constrained('contribution_schedules')->cascadeOnDelete();
            $t->uuid('transaction_reference')->nullable();  // links to central ledger
            $t->bigInteger('amount_minor');
            $t->date('paid_on');
            $t->enum('mode', ['mobile_money', 'cash', 'group']);
            $t->string('receipt_path')->nullable();
            $t->timestamps();
        });

        // --- Care claims chain ---
        Schema::create('care_claims', function (Blueprint $t) {   // recours
            $t->id();
            $t->foreignId('member_id')->constrained('members');
            $t->foreignId('dependent_id')->nullable()->constrained('dependents');
            $t->date('opened_on');
            $t->string('reason', 255)->nullable();
            $t->enum('status', ['open', 'closed'])->default('open');
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('prestations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('care_claim_id')->constrained('care_claims')->cascadeOnDelete();
            $t->foreignId('act_type_id')->constrained('act_types');
            $t->unsignedBigInteger('provider_id');          // central providers registry
            $t->foreignId('guarantee_version_id')->constrained('guarantee_versions');
            $t->date('care_date');
            $t->bigInteger('total_minor');
            $t->bigInteger('mutual_part_minor');
            $t->bigInteger('beneficiary_part_minor');       // copay
            $t->enum('status', ['captured', 'controlled', 'validated', 'rejected'])->default('captured');
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('prescribed_medicines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('prestation_id')->constrained('prestations')->cascadeOnDelete();
            $t->foreignId('medicine_id')->constrained('medicines');
            $t->unsignedInteger('quantity');
            $t->bigInteger('amount_minor');
            $t->timestamps();
        });

        Schema::create('provider_invoices', function (Blueprint $t) {  // tiers payant
            $t->id();
            $t->unsignedBigInteger('provider_id');
            $t->foreignId('care_claim_id')->nullable()->constrained('care_claims');
            $t->bigInteger('amount_minor');
            $t->enum('status', ['captured', 'processed', 'prepared', 'paid', 'rejected'])->default('captured');
            $t->string('supporting_doc_path')->nullable();
            $t->unsignedBigInteger('captured_by');
            $t->unsignedBigInteger('validated_by')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('reimbursements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('care_claim_id')->constrained('care_claims')->cascadeOnDelete();
            $t->bigInteger('requested_minor');
            $t->bigInteger('granted_minor')->nullable();
            $t->enum('status', ['requested', 'processed', 'prepared', 'paid', 'rejected'])->default('requested');
            $t->json('supporting_docs')->nullable();
            $t->unsignedBigInteger('captured_by');
            $t->unsignedBigInteger('validated_by')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        // --- Treasury ---
        Schema::create('treasury_movements', function (Blueprint $t) {
            $t->id();
            $t->enum('direction', ['inflow', 'outflow']);
            $t->enum('source', ['membership_fee', 'contribution', 'provider_invoice', 'reimbursement', 'other']);
            $t->bigInteger('amount_minor');
            $t->enum('mode', ['cash', 'bank', 'mobile_money']);
            $t->string('linked_reference', 60)->nullable();
            $t->string('supporting_doc_path')->nullable();
            $t->date('moved_on');
            $t->unsignedBigInteger('accounting_day_id')->nullable();
            $t->timestamps();
        });

        // --- SYSCOHADA accounting ---
        Schema::create('fiscal_years', function (Blueprint $t) {
            $t->id(); $t->string('label', 40); $t->date('start_date'); $t->date('end_date');
            $t->enum('status', ['open', 'closed'])->default('open'); $t->timestamps();
        });
        Schema::create('accounting_periods', function (Blueprint $t) {
            $t->id(); $t->foreignId('fiscal_year_id')->constrained('fiscal_years')->cascadeOnDelete();
            $t->string('label', 40); $t->enum('status', ['open', 'closed'])->default('open'); $t->timestamps();
        });
        Schema::create('accounting_days', function (Blueprint $t) {
            $t->id(); $t->foreignId('accounting_period_id')->constrained('accounting_periods')->cascadeOnDelete();
            $t->date('day'); $t->bigInteger('theoretical_balance_minor')->default(0);
            $t->bigInteger('actual_balance_minor')->default(0);
            $t->enum('status', ['open', 'closed'])->default('open'); $t->timestamps();
        });
        Schema::create('accounts', function (Blueprint $t) {       // plan comptable SYSCOHADA
            $t->id(); $t->string('number', 20)->unique(); $t->string('label', 180);
            $t->unsignedTinyInteger('class'); $t->timestamps();
        });
        Schema::create('accounting_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('accounting_day_id')->constrained('accounting_days');
            $t->string('piece', 60);
            $t->foreignId('debit_account_id')->constrained('accounts');
            $t->foreignId('credit_account_id')->constrained('accounts');
            $t->bigInteger('amount_minor');                 // debit == credit (app-enforced)
            $t->string('label', 255)->nullable();
            $t->enum('status', ['captured', 'validated', 'posted'])->default('captured');
            $t->unsignedBigInteger('reversed_entry_id')->nullable(); // counter-entry
            $t->timestamps();
        });

        // --- Anti-fraud alerts ---
        Schema::create('alerts', function (Blueprint $t) {
            $t->id();
            $t->enum('category', ['duplicate', 'overconsumption', 'atypical_provider', 'temporal', 'financial']);
            $t->enum('level', ['normal', 'watch', 'critical']); // green / amber / red
            $t->string('object_type', 100)->nullable();
            $t->unsignedBigInteger('object_id')->nullable();
            $t->json('details')->nullable();
            $t->enum('status', ['open', 'cleared'])->default('open');
            $t->unsignedBigInteger('cleared_by')->nullable();
            $t->timestamps();
        });

        // --- Transverse ---
        Schema::create('documents', function (Blueprint $t) {
            $t->id(); $t->string('type', 60); $t->string('object_type', 100)->nullable();
            $t->unsignedBigInteger('object_id')->nullable(); $t->string('path'); $t->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('user_id')->nullable();
            $t->string('action', 60); $t->string('object_type', 100)->nullable();
            $t->unsignedBigInteger('object_id')->nullable();
            $t->json('before')->nullable(); $t->json('after')->nullable();
            $t->string('ip', 45)->nullable(); $t->timestamp('occurred_at', 3)->useCurrent();
        });
    }

    public function down(): void
    {
        foreach ([
            'audit_logs','documents','alerts','accounting_entries','accounts','accounting_days',
            'accounting_periods','fiscal_years','treasury_movements','reimbursements','provider_invoices',
            'prescribed_medicines','prestations','care_claims','contribution_payments','contribution_schedules',
        ] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
