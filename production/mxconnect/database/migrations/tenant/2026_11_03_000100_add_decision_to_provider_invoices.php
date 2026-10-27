<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Base de CHAQUE mutuelle — migration additive (2026-11-03) : registre des factures des
 * prestataires. decision_note = motif d'un rejet ; decided_at = date du paiement ou du rejet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('provider_invoices', 'decision_note')) {
            Schema::table('provider_invoices', function (Blueprint $t) {
                $t->string('decision_note', 500)->nullable()->after('validated_by');
                $t->timestamp('decided_at')->nullable()->after('decision_note');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('provider_invoices', 'decision_note')) {
            Schema::table('provider_invoices', fn (Blueprint $t) => $t->dropColumn(['decision_note', 'decided_at']));
        }
    }
};
