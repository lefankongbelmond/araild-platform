<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Base CENTRALE — App Center (2026-11-13), migration additive.
 *  - app_center_apps : les applications installables (mutuelle, social) et leurs textes,
 *    version, guides, liens, fichier APK signé ;
 *  - app_center_stats : compteurs JOURNALIERS anonymes (clics, fenêtres d'installation,
 *    installations confirmées, lancements…) par application, système, navigateur, version ;
 *  - app_center_installs : une ligne par installation confirmée, identifiée par l'empreinte
 *    d'un identifiant aléatoire tiré par l'appareil (aucun compte, aucune adresse IP) ;
 *    sert à compter les installations actives (lancées dans les 30 derniers jours).
 * Les deux applications sont créées par App\Support\AppCenter::seed().
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        $s = Schema::connection('central');

        if (! $s->hasTable('app_center_apps')) {
            $s->create('app_center_apps', function (Blueprint $t) {
                $t->id();
                $t->string('key', 20)->unique();               // mutuelle | social
                $t->boolean('enabled')->default(true);
                $t->string('name', 80);
                $t->string('tagline', 160)->nullable();
                $t->text('description');
                $t->json('features')->nullable();              // liste de fonctionnalités
                $t->boolean('version_auto')->default(true);    // suit le fichier VERSION de la plateforme
                $t->string('version', 20)->default('1.0.0');
                $t->date('released_on')->nullable();
                $t->string('icon_path', 190)->nullable();      // icône 512 px (PNG) déposée
                $t->text('guide_note')->nullable();            // consigne ajoutée aux guides
                $t->string('play_store_url', 255)->nullable();
                $t->string('apk_path', 190)->nullable();       // fichier sur disque privé
                $t->string('apk_version', 30)->nullable();
                $t->string('apk_sha256', 64)->nullable();      // empreinte du FICHIER
                $t->string('apk_signer', 120)->nullable();     // empreinte du CERTIFICAT vérifiée
                $t->unsignedInteger('apk_size')->nullable();
                $t->timestamp('apk_uploaded_at')->nullable();
                $t->timestamps();
            });
        }
        if (! $s->hasTable('app_center_stats')) {
            $s->create('app_center_stats', function (Blueprint $t) {
                $t->id();
                $t->date('day');
                $t->string('app', 20);
                $t->string('event', 24);   // click_pc | click_android | prompt | accepted | dismissed | installed | launched | guide | apk_download | open
                $t->string('os', 12);      // windows | macos | linux | android | ios | chromeos | other
                $t->string('browser', 12); // chrome | edge | firefox | safari | samsung | opera | other
                $t->string('version', 20)->default('');
                $t->unsignedInteger('count')->default(0);
                $t->unique(['day', 'app', 'event', 'os', 'browser', 'version'], 'app_center_stats_key');
                $t->index(['app', 'event', 'day']);
            });
        }
        if (! $s->hasTable('app_center_installs')) {
            $s->create('app_center_installs', function (Blueprint $t) {
                $t->id();
                $t->string('app', 20);
                $t->string('mutual_id', 64)->nullable();   // application d'une mutuelle (pas de donnée personnelle)
                $t->char('device_hash', 64);               // sha256(clé de l'appli + identifiant aléatoire de l'appareil)
                $t->string('os', 12);
                $t->string('browser', 12);
                $t->string('form', 8);                     // pc | mobile
                $t->string('version', 20)->default('');
                $t->string('source', 12);                  // installed (événement du navigateur) | launched (ouverture en mode application)
                $t->timestamp('installed_at');
                $t->timestamp('last_launch_at')->nullable();
                $t->timestamps();
                $t->unique(['app', 'device_hash']);
                $t->index(['app', 'last_launch_at']);
            });
        }
    }

    public function down(): void
    {
        $s = Schema::connection('central');
        $s->dropIfExists('app_center_installs');
        $s->dropIfExists('app_center_stats');
        $s->dropIfExists('app_center_apps');
    }
};
