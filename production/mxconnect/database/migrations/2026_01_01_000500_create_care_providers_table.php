<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CENTRAL — care-provider registry (hospitals, clinics, pharmacies, labs).
 * Providers are network-wide; each mutual's guarantee version whitelists the
 * provider ids it accepts (guarantee_versions.provider_network). This also seeds
 * the Phase-4 shared provider directory.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_providers', function (Blueprint $t) {
            $t->id();
            $t->string('name', 180);
            $t->enum('type', ['hospital', 'clinic', 'health_center', 'pharmacy', 'laboratory', 'other'])->default('other');
            $t->foreignId('country_id')->nullable()->constrained('countries');
            $t->string('locality', 120)->nullable();
            $t->string('phone', 30)->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->index(['country_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_providers');
    }
};
