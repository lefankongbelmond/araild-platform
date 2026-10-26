<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SaaS billing (CDC Phase 4, §32) — how the platform charges each mutual. Central:
 * plans, one billing subscription per mutual, and the invoices generated per period.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('billing_plans', function (Blueprint $t) {
            $t->id();
            $t->string('code', 40)->unique();
            $t->string('name', 120);
            $t->bigInteger('price_minor');
            $t->unsignedBigInteger('currency_id')->nullable();
            $t->enum('interval', ['monthly', 'annual'])->default('monthly');
            $t->unsignedInteger('max_members')->nullable();   // tier ceiling; null = unlimited
            $t->json('features')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::connection('central')->create('billing_subscriptions', function (Blueprint $t) {
            $t->id();
            $t->string('mutual_id');
            $t->foreign('mutual_id')->references('id')->on('mutuals')->cascadeOnDelete();
            $t->foreignId('billing_plan_id')->constrained('billing_plans');
            $t->enum('status', ['trialing', 'active', 'past_due', 'suspended', 'cancelled'])->default('active');
            $t->date('started_on');
            $t->date('current_period_start');
            $t->date('current_period_end');
            $t->date('trial_ends_on')->nullable();
            $t->timestamps();
            $t->unique('mutual_id');
        });

        Schema::connection('central')->create('billing_invoices', function (Blueprint $t) {
            $t->id();
            $t->string('number', 24)->unique();
            $t->string('mutual_id');
            $t->foreign('mutual_id')->references('id')->on('mutuals')->cascadeOnDelete();
            $t->foreignId('billing_subscription_id')->constrained('billing_subscriptions')->cascadeOnDelete();
            $t->bigInteger('amount_minor');
            $t->unsignedBigInteger('currency_id')->nullable();
            $t->enum('status', ['issued', 'paid', 'overdue', 'void'])->default('issued');
            $t->date('period_start');
            $t->date('period_end');
            $t->date('issued_on');
            $t->date('due_on');
            $t->date('paid_on')->nullable();
            $t->timestamps();
            $t->index(['mutual_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('billing_invoices');
        Schema::connection('central')->dropIfExists('billing_subscriptions');
        Schema::connection('central')->dropIfExists('billing_plans');
    }
};
