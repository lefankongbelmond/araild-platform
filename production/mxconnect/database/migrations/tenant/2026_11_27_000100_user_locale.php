<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Base de CHAQUE mutuelle — M1 multilingue (2026-11-27) : langue préférée du personnel. ADDITIVE. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'locale')) {
            Schema::table('users', fn (Blueprint $t) => $t->string('locale', 10)->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'locale')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('locale'));
        }
    }
};
