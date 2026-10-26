<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Réseau social (base CENTRALE) — migration additive « design Lot 2 » :
 *  - domaines d'intervention (Santé, Protection sociale, Éducation…) sur les fiches
 *    d'organisation et les projets ;
 *  - avancement déclaré (%) et bénéficiaires d'un projet.
 * Les mutuelles de santé existantes reçoivent « Santé » + « Protection sociale »
 * (déduit de leur catégorie, modifiable ensuite).
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        $s = Schema::connection('central');

        if (! $s->hasColumn('mutual_profiles', 'domains')) {
            $s->table('mutual_profiles', fn (Blueprint $t) => $t->json('domains')->nullable());
            DB::connection('central')->table('mutual_profiles')->where('category', 'mutuelle')
                ->update(['domains' => json_encode(['sante', 'protection_sociale'])]);
        }
        if (! $s->hasColumn('social_projects', 'domains')) {
            $s->table('social_projects', function (Blueprint $t) {
                $t->json('domains')->nullable();
                $t->unsignedTinyInteger('progress')->nullable();          // 0–100, déclaré par l'organisation
                $t->string('beneficiaries', 255)->nullable();             // texte libre agrégé, jamais nominatif
            });
        }
    }

    public function down(): void
    {
        $s = Schema::connection('central');
        if ($s->hasColumn('social_projects', 'domains')) {
            $s->table('social_projects', fn (Blueprint $t) => $t->dropColumn(['domains', 'progress', 'beneficiaries']));
        }
        if ($s->hasColumn('mutual_profiles', 'domains')) {
            $s->table('mutual_profiles', fn (Blueprint $t) => $t->dropColumn('domains'));
        }
    }
};
