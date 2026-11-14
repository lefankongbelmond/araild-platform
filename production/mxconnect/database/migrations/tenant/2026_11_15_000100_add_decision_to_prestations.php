<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Base de CHAQUE mutuelle — migration additive (2026-11-15, Portail prestataire V2, phase 1).
 * prestations.decision_note = motif d'un rejet (obligatoire depuis cette version, montré au
 * prestataire) ; decided_at = date de la validation ou du rejet. Aucune donnée existante modifiée.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('prestations', 'decision_note')) {
            Schema::table('prestations', function (Blueprint $t) {
                $t->string('decision_note', 500)->nullable()->after('status');
                $t->timestamp('decided_at')->nullable()->after('decision_note');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('prestations', 'decision_note')) {
            Schema::table('prestations', fn (Blueprint $t) => $t->dropColumn(['decision_note', 'decided_at']));
        }
    }
};
