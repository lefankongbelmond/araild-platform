<?php

namespace App\Console\Commands;

use App\Models\Central\Mutual;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Après le dépôt d'une nouvelle version : migrations de la base centrale puis
 * des bases des mutuelles réelles. Lancé automatiquement par index.php au premier
 * passage d'une nouvelle version (hébergement sans terminal), ou à la main.
 */
class PostUpdate extends Command
{
    protected $signature = 'mxconnect:post-update';
    protected $description = 'Applique les migrations après le dépôt d\'une nouvelle version.';

    public function handle(): int
    {
        $this->line(trim(Artisan::call('migrate', ['--force' => true]) === 0 ? Artisan::output() : 'Échec migrate : ' . Artisan::output()));
        $ids = Mutual::operational()->pluck('id')->all();
        if ($ids) {
            Artisan::call('tenants:migrate', ['--force' => true, '--tenants' => $ids]);
            $this->line(trim(Artisan::output()));
        }

        // Images publiées avant le passage au WebP : conversion par lots courts
        // (la suite est reprise par le cron et aux mises à jour suivantes).
        try {
            Artisan::call('mxconnect:media-webp', ['--seconds' => 20]);
            $this->line(trim(Artisan::output()));
        } catch (\Throwable $e) {
            $this->warn('Conversion WebP : ' . $e->getMessage());
        }

        return self::SUCCESS;
    }
}
