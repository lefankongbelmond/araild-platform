<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Réseau social — livraison 4 : préférences e-mail (membres), adresse d'alerte (mutuelles), jetons de réinitialisation. */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::connection('central')->table('public_accounts', function (Blueprint $t) {
            $t->boolean('email_notifications')->default(true)->after('profile_visibility');
        });
        Schema::connection('central')->table('mutual_profiles', function (Blueprint $t) {
            $t->string('alert_email', 180)->nullable()->after('email');   // privé : jamais affiché
        });
        // Jetons « mot de passe oublié » des comptes membres (table distincte de celle du personnel).
        Schema::connection('central')->create('public_password_reset_tokens', function (Blueprint $t) {
            $t->string('email')->primary();
            $t->string('token');
            $t->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('public_password_reset_tokens');
        Schema::connection('central')->table('public_accounts', fn (Blueprint $t) => $t->dropColumn('email_notifications'));
        Schema::connection('central')->table('mutual_profiles', fn (Blueprint $t) => $t->dropColumn('alert_email'));
    }
};
