<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Base CENTRALE — M1 multilingue (2026-11-27). ADDITIVE.
 *  - locales.direction (ltr/rtl) et locales.native_name ;
 *  - les 7 langues livrées (fr, en, pt, es, ar, sw, ha) : créées si absentes, INACTIVES sauf le
 *    français et l'anglais (le super-admin les active quand il le souhaite) ; une langue déjà
 *    présente garde son état ;
 *  - préférence de langue des comptes : public_accounts, provider_accounts, network_users.
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        $db = Schema::connection('central');
        $db->table('locales', function (Blueprint $t) use ($db) {
            if (! $db->hasColumn('locales', 'direction')) {
                $t->string('direction', 3)->default('ltr')->after('name');
            }
            if (! $db->hasColumn('locales', 'native_name')) {
                $t->string('native_name', 80)->nullable()->after('name');
            }
        });
        foreach (['public_accounts', 'provider_accounts', 'network_users'] as $table) {
            if ($db->hasTable($table) && ! $db->hasColumn($table, 'locale')) {
                $db->table($table, fn (Blueprint $t) => $t->string('locale', 10)->nullable());
            }
        }

        $langs = [
            'fr' => ['French', 'Français', 'ltr', true], 'en' => ['English', 'English', 'ltr', true],
            'pt' => ['Portuguese', 'Português', 'ltr', false], 'es' => ['Spanish', 'Español', 'ltr', false],
            'ar' => ['Arabic', 'العربية', 'rtl', false], 'sw' => ['Swahili', 'Kiswahili', 'ltr', false],
            'ha' => ['Hausa', 'Hausa', 'ltr', false],
        ];
        foreach ($langs as $code => [$name, $native, $dir, $active]) {
            $row = DB::connection('central')->table('locales')->where('code', $code)->first();
            if ($row) {
                DB::connection('central')->table('locales')->where('id', $row->id)->update(['direction' => $dir, 'native_name' => $native, 'updated_at' => now()]);
            } else {
                DB::connection('central')->table('locales')->insert(['code' => $code, 'name' => $name, 'native_name' => $native, 'direction' => $dir,
                    'active' => $active, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        $db = Schema::connection('central');
        foreach (['public_accounts', 'provider_accounts', 'network_users'] as $table) {
            if ($db->hasColumn($table, 'locale')) {
                $db->table($table, fn (Blueprint $t) => $t->dropColumn('locale'));
            }
        }
        foreach (['direction', 'native_name'] as $c) {
            if ($db->hasColumn('locales', $c)) {
                $db->table('locales', fn (Blueprint $t) => $t->dropColumn($c));
            }
        }
    }
};
