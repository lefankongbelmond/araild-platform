<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Password reset tokens (central) — backs both the 'users' and 'network_users'
 * password brokers declared in config/auth.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('password_reset_tokens', function (Blueprint $t) {
            $t->string('email')->primary();
            $t->string('token');
            $t->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('password_reset_tokens');
    }
};
