<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Published public offer summary per mutual (CDC Phase 4, §30). Central and
 * denormalised so the public comparator is fast and never touches tenant DBs;
 * refreshed from tenant guarantee data by mxconnect:sync-offers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('mutual_offers', function (Blueprint $t) {
            $t->id();
            $t->string('mutual_id');
            $t->foreign('mutual_id')->references('id')->on('mutuals')->cascadeOnDelete();
            $t->unsignedBigInteger('currency_id')->nullable();
            $t->unsignedInteger('guarantees_count')->default(0);
            $t->decimal('coverage_min', 5, 2)->nullable();
            $t->decimal('coverage_max', 5, 2)->nullable();
            $t->bigInteger('monthly_contribution_min_minor')->nullable();
            $t->bigInteger('monthly_contribution_max_minor')->nullable();
            $t->bigInteger('membership_fee_min_minor')->nullable();
            $t->bigInteger('membership_fee_max_minor')->nullable();
            $t->json('highlights')->nullable();
            $t->boolean('published')->default(true);
            $t->timestamp('synced_at')->nullable();
            $t->timestamps();
            $t->unique('mutual_id');
        });
    }

    public function down(): void { Schema::connection('central')->dropIfExists('mutual_offers'); }
};
