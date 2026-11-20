<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Base CENTRALE — multilingue M4 (2026-12-03). ADDITIVE :
 *  - provider_notifications.i18n : texte de l'avis sous forme traduisible (clé française + valeurs),
 *    pour l'afficher dans la langue de CHAQUE personne de l'établissement. Les colonnes title/body
 *    gardent le texte français (anciens avis, exports) ; un avis sans i18n s'affiche tel qu'enregistré.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('central');
        if ($s->hasTable('provider_notifications') && ! $s->hasColumn('provider_notifications', 'i18n')) {
            $s->table('provider_notifications', fn (Blueprint $t) => $t->json('i18n')->nullable());
        }
    }

    public function down(): void
    {
        $s = Schema::connection('central');
        if ($s->hasTable('provider_notifications') && $s->hasColumn('provider_notifications', 'i18n')) {
            $s->table('provider_notifications', fn (Blueprint $t) => $t->dropColumn('i18n'));
        }
    }
};
