<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenancy registry + public identity (CENTRAL).
 * `mutuals` is the tenant registry; `domains` resolves subdomain -> tenant.
 * `public_accounts` is the grand-public login, separated from tenant-side members.
 */
return new class extends Migration
{
    public function up(): void
    {
        // stancl/tenancy expects a `tenants` table with a string id + json data;
        // we extend it with real relational columns for our domain.
        Schema::connection('central')->create('mutuals', function (Blueprint $t) {
            $t->string('id')->primary();                 // tenant id (stancl) e.g. "mut_01"
            $t->string('name', 180);
            $t->string('slug', 60)->unique();            // subdomain label
            $t->foreignId('country_id')->constrained('countries');
            $t->foreignId('currency_id')->constrained('currencies');   // may differ from country default
            $t->foreignId('locale_id')->constrained('locales');
            $t->string('region', 120)->nullable();
            $t->string('city', 120)->nullable();
            $t->string('logo_path')->nullable();
            $t->enum('status', ['pending', 'approved', 'suspended'])->default('pending');
            $t->date('approved_at')->nullable();
            $t->unsignedBigInteger('approved_by')->nullable();
            $t->json('data')->nullable();                // stancl payload
            $t->timestamps();
        });

        // Payment provider config PER MUTUAL (its own merchant credentials — encrypted).
        Schema::connection('central')->create('mutual_payment_configs', function (Blueprint $t) {
            $t->id();
            $t->string('mutual_id');
            $t->foreignId('payment_provider_id')->constrained('payment_providers');
            $t->text('merchant_id');                     // encrypted cast in model
            $t->text('credentials')->nullable();         // encrypted JSON (API keys of THE MUTUAL)
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->foreign('mutual_id')->references('id')->on('mutuals')->cascadeOnDelete();
            $t->unique(['mutual_id', 'payment_provider_id']);
        });

        Schema::connection('central')->create('domains', function (Blueprint $t) {
            $t->id();
            $t->string('domain', 255)->unique();
            $t->string('mutual_id');
            $t->timestamps();
            $t->foreign('mutual_id')->references('id')->on('mutuals')->cascadeOnDelete();
        });

        // Network-level users (super admin, security admin, moderator).
        Schema::connection('central')->create('network_users', function (Blueprint $t) {
            $t->id();
            $t->string('first_name', 120);
            $t->string('last_name', 120);
            $t->string('email', 180)->unique();
            $t->string('phone', 20)->nullable();
            $t->string('password');
            $t->text('mfa_secret')->nullable();          // encrypted
            $t->boolean('mfa_enabled')->default(false);
            $t->boolean('active')->default(true);
            $t->rememberToken();
            $t->timestamps();
            $t->softDeletes();
        });

        // Grand-public identity/login (a person may belong to several mutuals).
        Schema::connection('central')->create('public_accounts', function (Blueprint $t) {
            $t->id();
            $t->string('phone', 20)->unique();           // E.164 login
            $t->string('email', 180)->nullable();
            $t->string('first_name', 120);
            $t->string('last_name', 120);
            $t->string('password');
            $t->foreignId('locale_id')->nullable()->constrained('locales');
            $t->timestamp('phone_verified_at')->nullable();
            $t->rememberToken();
            $t->timestamps();
        });

        // Bridge public_account <-> a member row inside a tenant DB (no cross-db FK).
        Schema::connection('central')->create('member_links', function (Blueprint $t) {
            $t->id();
            $t->foreignId('public_account_id')->constrained('public_accounts')->cascadeOnDelete();
            $t->string('mutual_id');
            $t->unsignedBigInteger('member_id');         // id inside the tenant DB
            $t->string('member_code', 40);
            $t->timestamps();
            $t->foreign('mutual_id')->references('id')->on('mutuals')->cascadeOnDelete();
            $t->unique(['public_account_id', 'mutual_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('member_links');
        Schema::connection('central')->dropIfExists('public_accounts');
        Schema::connection('central')->dropIfExists('network_users');
        Schema::connection('central')->dropIfExists('domains');
        Schema::connection('central')->dropIfExists('mutual_payment_configs');
        Schema::connection('central')->dropIfExists('mutuals');
    }
};
