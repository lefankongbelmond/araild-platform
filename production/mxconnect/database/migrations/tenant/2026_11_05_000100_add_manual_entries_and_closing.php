<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Base de CHAQUE mutuelle — migration additive (2026-11-05) : opérations diverses saisies à
 * la main (saisie ≠ validation), extournes, clôture d'exercice.
 *  - accounting_entries.kind : auto (trésorerie), manual (opération diverse), reversal
 *    (extourne), closing (écriture de clôture) ; statut « rejected » pour une saisie refusée ;
 *  - fiscal_years : qui a clôturé, quand, avec quel résultat ; historique des réouvertures.
 * Les écritures existantes deviennent « auto » : rien d'autre ne change.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('accounting_entries', 'kind')) {
            Schema::table('accounting_entries', function (Blueprint $t) {
                $t->enum('status', ['captured', 'validated', 'posted', 'rejected'])->default('captured')->change();
                $t->string('kind', 20)->default('auto')->after('status');
                $t->unsignedBigInteger('captured_by')->nullable()->after('reversed_entry_id');
                $t->unsignedBigInteger('validated_by')->nullable()->after('captured_by');
                $t->timestamp('validated_at')->nullable()->after('validated_by');
                $t->string('decision_note', 500)->nullable()->after('validated_at');
                $t->index(['kind', 'status']);
            });
        }
        if (! Schema::hasColumn('fiscal_years', 'closed_at')) {
            Schema::table('fiscal_years', function (Blueprint $t) {
                $t->timestamp('closed_at')->nullable()->after('status');
                $t->unsignedBigInteger('closed_by')->nullable()->after('closed_at');
                $t->bigInteger('result_minor')->nullable()->after('closed_by');
                $t->json('history')->nullable()->after('result_minor');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('accounting_entries', 'kind')) {
            Schema::table('accounting_entries', function (Blueprint $t) {
                $t->dropIndex(['kind', 'status']);
                $t->dropColumn(['kind', 'captured_by', 'validated_by', 'validated_at', 'decision_note']);
            });
        }
        if (Schema::hasColumn('fiscal_years', 'closed_at')) {
            Schema::table('fiscal_years', fn (Blueprint $t) => $t->dropColumn(['closed_at', 'closed_by', 'result_minor', 'history']));
        }
    }
};
