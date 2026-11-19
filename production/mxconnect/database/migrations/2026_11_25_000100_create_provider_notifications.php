<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Base CENTRALE — Portail prestataire V2, phase 6 (2026-11-25). ADDITIVE :
 *  - provider_notifications : avis envoyés à un établissement par une mutuelle (prise en charge
 *    validée ou rejetée, facture payée ou rejetée, accord préalable décidé, affiliation décidée).
 *    Aucune donnée médicale. Chaque avis porte le droit d'établissement nécessaire pour le voir ;
 *  - provider_memberships.notifications_seen_id : dernier avis vu par la personne dans cet établissement.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('central');
        if (! $s->hasTable('provider_notifications')) {
            $s->create('provider_notifications', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('care_provider_id');
                $t->string('mutual_id')->nullable();
                $t->string('kind', 40);
                $t->string('title', 160);
                $t->text('body')->nullable();
                $t->string('target', 20)->nullable();      // preauth, claims, invoices, mutuals
                $t->string('permission', 40);              // droit d'établissement nécessaire (ProviderAccess)
                $t->timestamp('created_at')->useCurrent();
                $t->index(['care_provider_id', 'id']);
            });
        }
        if ($s->hasTable('provider_memberships') && ! $s->hasColumn('provider_memberships', 'notifications_seen_id')) {
            $s->table('provider_memberships', fn (Blueprint $t) => $t->unsignedBigInteger('notifications_seen_id')->default(0));
        }
    }

    public function down(): void
    {
        $s = Schema::connection('central');
        $s->dropIfExists('provider_notifications');
        if ($s->hasColumn('provider_memberships', 'notifications_seen_id')) {
            $s->table('provider_memberships', fn (Blueprint $t) => $t->dropColumn('notifications_seen_id'));
        }
    }
};
