<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Base de CHAQUE mutuelle — migration additive (2026-11-06) : encaissement des cotisations au
 * guichet. Un encaissement (collection_ref) peut régler plusieurs échéances, en tout ou en
 * partie ; mode « bank » ajouté (virement, dépôt) ; référence donnée par le trésorier
 * (n° de transaction Mobile Money reçue hors plateforme, bordereau…) ; qui a encaissé.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('contribution_payments', 'collection_ref')) {
            Schema::table('contribution_payments', function (Blueprint $t) {
                $t->enum('mode', ['mobile_money', 'cash', 'group', 'bank'])->change();
                $t->string('collection_ref', 40)->nullable()->after('mode');
                $t->string('reference', 60)->nullable()->after('collection_ref');
                $t->unsignedBigInteger('recorded_by')->nullable()->after('reference');
                $t->index('collection_ref');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('contribution_payments', 'collection_ref')) {
            Schema::table('contribution_payments', function (Blueprint $t) {
                $t->dropIndex(['collection_ref']);
                $t->dropColumn(['collection_ref', 'reference', 'recorded_by']);
            });
        }
    }
};
