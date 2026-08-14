<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-country configuration layer (CENTRAL).
 * Countries, currencies, payment providers and locales are DATA, not code.
 * Adding a country/provider/currency never requires a code change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('currencies', function (Blueprint $t) {
            $t->id();
            $t->string('code', 3)->unique();          // ISO 4217, e.g. XAF, USD
            $t->string('name', 80);
            $t->string('symbol', 8);
            $t->unsignedTinyInteger('minor_unit')->default(0); // XAF=0, USD=2
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::connection('central')->create('locales', function (Blueprint $t) {
            $t->id();
            $t->string('code', 10)->unique();          // fr, en, pt
            $t->string('name', 80);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::connection('central')->create('countries', function (Blueprint $t) {
            $t->id();
            $t->string('iso2', 2)->unique();           // CM, CI, SN...
            $t->string('name', 120);
            $t->foreignId('default_currency_id')->constrained('currencies');
            $t->foreignId('default_locale_id')->constrained('locales');
            $t->string('phone_prefix', 6)->nullable(); // +237
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::connection('central')->create('payment_providers', function (Blueprint $t) {
            $t->id();
            $t->string('code', 40)->unique();          // mtn_momo, orange_money, wave...
            $t->string('name', 120);
            $t->string('driver')->nullable();          // FQCN of the provider driver class
            $t->json('config_schema')->nullable();     // fields the mutual must fill (per-provider)
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        // Which providers are available in which country (many-to-many).
        Schema::connection('central')->create('country_payment_provider', function (Blueprint $t) {
            $t->id();
            $t->foreignId('country_id')->constrained('countries')->cascadeOnDelete();
            $t->foreignId('payment_provider_id')->constrained('payment_providers')->cascadeOnDelete();
            $t->boolean('active')->default(true);
            $t->unique(['country_id', 'payment_provider_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('country_payment_provider');
        Schema::connection('central')->dropIfExists('payment_providers');
        Schema::connection('central')->dropIfExists('countries');
        Schema::connection('central')->dropIfExists('locales');
        Schema::connection('central')->dropIfExists('currencies');
    }
};
