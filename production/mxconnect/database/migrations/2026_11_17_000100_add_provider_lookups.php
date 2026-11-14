<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CENTRALE — Portail prestataire V2, phase 3 (2026-11-17), migration additive :
 *  - provider_handoffs.payload : code de carte transmis au portail de la mutuelle (pré-rempli,
 *    la vérification reste faite et journalisée DANS la mutuelle) ;
 *  - provider_lookups : journal des recherches par code ou QR faites depuis l'espace central
 *    (empreinte du code, jamais le code en clair ; nombre de mutuelles où il existe).
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        $s = Schema::connection('central');
        if (! $s->hasColumn('provider_handoffs', 'payload')) {
            $s->table('provider_handoffs', fn (Blueprint $t) => $t->string('payload', 500)->nullable()->after('target'));
        }
        if (! $s->hasTable('provider_lookups')) {
            $s->create('provider_lookups', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('provider_account_id');
                $t->unsignedBigInteger('care_provider_id');
                $t->string('mode', 8);                  // code | qr
                $t->char('code_hash', 64);
                $t->unsignedSmallInteger('matches')->default(0);
                $t->json('mutual_ids')->nullable();
                $t->string('ip', 45)->nullable();
                $t->timestamp('created_at')->nullable();
                $t->index(['care_provider_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        $s = Schema::connection('central');
        $s->dropIfExists('provider_lookups');
        if ($s->hasColumn('provider_handoffs', 'payload')) {
            $s->table('provider_handoffs', fn (Blueprint $t) => $t->dropColumn('payload'));
        }
    }
};
