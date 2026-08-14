<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TENANT schema — parameters + guarantees (with versioning).
 * Runs inside each mutual's own database. No mutual_id column: the DB *is* the mutual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('antennas', function (Blueprint $t) {
            $t->id();
            $t->string('name', 150);
            $t->string('locality', 120)->nullable();
            $t->string('manager', 120)->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('act_types', function (Blueprint $t) {   // types_prestation
            $t->id();
            $t->string('code', 30)->unique();
            $t->string('label', 180);
            $t->string('category', 80)->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('medicines', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->string('label', 180);
            $t->bigInteger('reference_price_minor')->default(0);
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('guarantees', function (Blueprint $t) {  // logical, stable entity
            $t->id();
            $t->string('code', 30)->unique();
            $t->string('name', 150);
            $t->text('description')->nullable();
            $t->enum('status', ['active', 'retired'])->default('active');
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('guarantee_versions', function (Blueprint $t) { // dated, immutable params
            $t->id();
            $t->foreignId('guarantee_id')->constrained('guarantees')->cascadeOnDelete();
            $t->unsignedInteger('version_no');
            $t->date('valid_from');
            $t->date('valid_to')->nullable();               // null = current
            $t->bigInteger('base_contribution_minor');
            $t->enum('periodicity', ['monthly', 'quarterly', 'annual']);
            $t->decimal('coverage_rate', 5, 2);             // % covered by the mutual
            $t->decimal('copay_rate', 5, 2);                // ticket modérateur %
            $t->bigInteger('membership_fee_minor')->default(0);
            $t->unsignedInteger('observation_days')->default(0); // waiting period
            $t->json('exclusions')->nullable();
            $t->json('provider_network')->nullable();       // central provider ids allowed
            $t->boolean('locked')->default(false);          // true once a claim used it
            $t->timestamps();
            $t->unique(['guarantee_id', 'version_no']);
        });

        Schema::create('ceilings', function (Blueprint $t) {    // plafonds
            $t->id();
            $t->foreignId('guarantee_version_id')->constrained('guarantee_versions')->cascadeOnDelete();
            $t->foreignId('act_type_id')->nullable()->constrained('act_types');
            $t->bigInteger('ceiling_minor');
            $t->enum('period', ['annual', 'per_act', 'per_stay'])->default('annual');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ceilings');
        Schema::dropIfExists('guarantee_versions');
        Schema::dropIfExists('guarantees');
        Schema::dropIfExists('medicines');
        Schema::dropIfExists('act_types');
        Schema::dropIfExists('antennas');
    }
};
