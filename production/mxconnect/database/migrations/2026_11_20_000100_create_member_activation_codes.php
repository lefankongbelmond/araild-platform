<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CENTRALE — portail adhérent (mission « Mutuelle paramétrable », L4, 2026-11-20).
 * Codes d'activation remis par la mutuelle à un adhérent inscrit au guichet : il les saisit dans
 * son compte MX-CONNECT (avec sa date de naissance) pour relier le compte à sa fiche (member_links).
 * Seule l'empreinte sha256 du code est stockée ; un code sert une fois et expire.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('central');
        if (! $s->hasTable('member_activation_codes')) {
            $s->create('member_activation_codes', function (Blueprint $t) {
                $t->id();
                $t->string('mutual_id');
                $t->unsignedBigInteger('member_id');
                $t->char('code_hash', 64)->unique();
                $t->timestamp('expires_at');
                $t->timestamp('used_at')->nullable();
                $t->unsignedBigInteger('used_by_account')->nullable();
                $t->unsignedBigInteger('created_by')->nullable();     // personnel de la mutuelle (id tenant)
                $t->timestamps();
                $t->index(['mutual_id', 'member_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('member_activation_codes');
    }
};
