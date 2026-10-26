<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Base de CHAQUE mutuelle — migration additive « équipe » :
 *  - must_change_password : mot de passe provisoire à remplacer à la prochaine connexion ;
 *  - last_login_at : dernière connexion (affichée dans « Équipe et rôles »).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'must_change_password')) {
            Schema::table('users', function (Blueprint $t) {
                $t->boolean('must_change_password')->default(false)->after('active');
                $t->timestamp('last_login_at')->nullable()->after('must_change_password');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'must_change_password')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['must_change_password', 'last_login_at']));
        }
    }
};
