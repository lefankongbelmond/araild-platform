<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Base de CHAQUE mutuelle — migration additive (2026-11-07) :
 *  - contribution_schedules.kind : « contribution » (échéance de cotisation, valeur des lignes
 *    existantes) ou « membership_fee » (droit d'adhésion, période « ADH ») ;
 *  - contribution_payments.deleted_at : un paiement annulé reste en base (trace), exclu des
 *    totaux ;
 *  - collection_cancellations : demandes d'annulation d'un encaissement (motif, 4 yeux).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('contribution_schedules', 'kind')) {
            Schema::table('contribution_schedules', function (Blueprint $t) {
                $t->string('kind', 20)->default('contribution')->after('subscription_id');
                $t->index('kind');
            });
        }
        if (! Schema::hasColumn('contribution_payments', 'deleted_at')) {
            Schema::table('contribution_payments', fn (Blueprint $t) => $t->softDeletes());
        }
        if (! Schema::hasTable('collection_cancellations')) {
            Schema::create('collection_cancellations', function (Blueprint $t) {
                $t->id();
                $t->string('collection_ref', 40)->index();
                $t->foreignId('member_id')->constrained('members');
                $t->bigInteger('amount_minor');
                $t->string('reason', 500);
                $t->unsignedBigInteger('requested_by');
                $t->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
                $t->unsignedBigInteger('decided_by')->nullable();
                $t->timestamp('decided_at')->nullable();
                $t->string('decision_note', 500)->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_cancellations');
        if (Schema::hasColumn('contribution_payments', 'deleted_at')) {
            Schema::table('contribution_payments', fn (Blueprint $t) => $t->dropSoftDeletes());
        }
        if (Schema::hasColumn('contribution_schedules', 'kind')) {
            Schema::table('contribution_schedules', function (Blueprint $t) {
                $t->dropIndex(['kind']);
                $t->dropColumn('kind');
            });
        }
    }
};
