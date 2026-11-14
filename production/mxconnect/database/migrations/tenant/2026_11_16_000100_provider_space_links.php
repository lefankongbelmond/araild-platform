<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Base de CHAQUE mutuelle — Portail prestataire V2, phase 2 (2026-11-16), migration additive :
 *  - users.provider_account_id : compte de la mutuelle ouvert AU NOM d'une personne de
 *    l'espace prestataire central (créé à sa première entrée, jamais utilisable par mot de passe) ;
 *  - provider_conventions : date de signature, délais de transmission et de paiement,
 *    conditions de facturation, historique des modifications ;
 *  - index central provider_affiliations rempli pour les conventions déjà existantes
 *    (une ligne par établissement conventionné : approved si active, suspended sinon).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'provider_account_id')) {
            Schema::table('users', function (Blueprint $t) {
                $t->unsignedBigInteger('provider_account_id')->nullable()->after('care_provider_id')->index();
            });
        }
        if (! Schema::hasColumn('provider_conventions', 'signed_on')) {
            Schema::table('provider_conventions', function (Blueprint $t) {
                $t->date('signed_on')->nullable()->after('reference');
                $t->unsignedSmallInteger('transmission_days')->nullable()->after('ends_on');
                $t->unsignedSmallInteger('payment_days')->nullable()->after('transmission_days');
                $t->text('billing_terms')->nullable()->after('payment_days');
                $t->json('history')->nullable()->after('notes');
            });
        }

        // Index central des conventions (pour l'espace prestataire multi-mutuelles).
        $mutualId = function_exists('tenant') ? tenant('id') : null;
        if ($mutualId && Schema::connection('central')->hasTable('provider_affiliations')) {
            foreach (DB::table('provider_conventions')->get(['care_provider_id', 'active']) as $c) {
                $exists = DB::connection('central')->table('provider_affiliations')
                    ->where('care_provider_id', $c->care_provider_id)->where('mutual_id', $mutualId)->exists();
                if (! $exists) {
                    DB::connection('central')->table('provider_affiliations')->insert([
                        'care_provider_id' => $c->care_provider_id, 'mutual_id' => $mutualId,
                        'status' => $c->active ? 'approved' : 'suspended', 'decided_at' => now(),
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('provider_conventions', 'signed_on')) {
            Schema::table('provider_conventions', fn (Blueprint $t) => $t->dropColumn(['signed_on', 'transmission_days', 'payment_days', 'billing_terms', 'history']));
        }
        if (Schema::hasColumn('users', 'provider_account_id')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('provider_account_id'));
        }
        // L'index central provider_affiliations est conservé (supprimé avec la migration centrale).
    }
};
