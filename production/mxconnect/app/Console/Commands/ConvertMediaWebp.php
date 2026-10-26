<?php

namespace App\Console\Commands;

use App\Services\Social\MediaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Convertit en WebP les images du réseau social publiées AVANT le passage au
 * WebP (JPEG/PNG) : photos des publications et miniatures, avatars, logos et
 * couvertures des mutuelles, couvertures des groupes, pièces images privées des
 * dossiers d'adhésion.
 *
 * - Reprise possible : chaque image est traitée puis la base est mise à jour ;
 *   un nouveau passage reprend là où le précédent s'est arrêté.
 * - --seconds limite la durée (lancé automatiquement après une mise à jour et
 *   par le cron, sur un hébergement où les requêtes sont limitées en temps).
 * - Une image illisible ou absente est laissée telle quelle (signalée), jamais supprimée.
 */
class ConvertMediaWebp extends Command
{
    protected $signature = 'mxconnect:media-webp {--seconds=0 : durée maximale (0 = sans limite)} {--dry : compte seulement}';
    protected $description = 'Convertit en WebP les images existantes du réseau social.';

    private float $deadline = 0;
    private int $done = 0;
    private int $failed = 0;

    public function handle(MediaService $media): int
    {
        if (! MediaService::webpSupported()) {
            $this->warn('Ce serveur ne sait pas écrire le WebP (GD sans WebP) : rien à faire.');

            return self::SUCCESS;
        }
        $this->deadline = ($s = (int) $this->option('seconds')) > 0 ? microtime(true) + $s : 0;
        $db = DB::connection('central');
        $legacy = fn ($q, $col) => $q->where(fn ($w) => $w->where($col, 'like', '%.jpg')->orWhere($col, 'like', '%.jpeg')->orWhere($col, 'like', '%.png'));

        // 1. Photos des publications (principale + miniature).
        if (Schema::connection('central')->hasTable('social_post_media')) {
            $q = $legacy($db->table('social_post_media'), 'path');
            if ($this->option('dry')) {
                $this->line('Photos de publications à convertir : ' . (clone $q)->count());
            } else {
                foreach ($q->lazyById(50) as $row) {
                    if ($this->timeUp()) break;
                    $this->convertPostMedia($media, $row);
                }
            }
        }

        // 2. Images uniques : [table, colonne, côté max].
        foreach ([['public_accounts', 'avatar_path', 512, 'id'], ['mutual_profiles', 'logo_path', 512, 'mutual_id'],
                  ['mutual_profiles', 'cover_path', 1600, 'mutual_id'], ['social_groups', 'cover_path', 1600, 'id']] as [$table, $col, $side, $pk]) {
            if (! Schema::connection('central')->hasTable($table) || ! Schema::connection('central')->hasColumn($table, $col)) continue;
            $q = $legacy($db->table($table)->whereNotNull($col), $col);
            if ($this->option('dry')) {
                $this->line("$table.$col à convertir : " . (clone $q)->count());
                continue;
            }
            foreach ($q->lazyById(50, $pk) as $row) {
                if ($this->timeUp()) break 2;
                $this->convertSingle($media, $table, $col, $row, $side, $pk);
            }
        }

        // 3. Pièces images privées des dossiers d'adhésion.
        if (Schema::connection('central')->hasTable('adhesion_documents') && ! $this->timeUp()) {
            $q = $db->table('adhesion_documents')->whereIn('mime', ['image/jpeg', 'image/png']);
            if ($this->option('dry')) {
                $this->line("Pièces d'adhésion à convertir : " . (clone $q)->count());
            } else {
                foreach ($q->lazyById(50) as $row) {
                    if ($this->timeUp()) break;
                    $this->convertPrivate($media, $row);
                }
            }
        }

        if (! $this->option('dry')) {
            $this->line("Images converties en WebP : {$this->done}" . ($this->failed ? " — illisibles laissées telles quelles : {$this->failed}" : '')
                . ($this->timeUp() ? ' — temps écoulé, la suite au prochain passage.' : ''));
        }

        return self::SUCCESS;
    }

    private function timeUp(): bool
    {
        return $this->deadline > 0 && microtime(true) > $this->deadline;
    }

    private function load(string $disk, string $path): ?\GdImage
    {
        $abs = Storage::disk($disk)->path($path);
        try {
            return is_file($abs) ? app(MediaService::class)->decode($abs) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function base(string $path): string
    {
        return preg_replace('/\.(jpe?g|png)$/i', '', $path);
    }

    private function convertPostMedia(MediaService $media, object $row): void
    {
        $src = $this->load('social', $row->path);
        if (! $src) { $this->failed++; return; }
        $new = $media->storeGd($src, $this->base($row->path), config('mxconnect.social.image_max_side'));
        DB::connection('central')->table('social_post_media')->where('id', $row->id)->update([
            'path' => $new['path'], 'thumb_path' => $new['thumb_path'], 'mime' => $new['mime'],
            'width' => $new['width'], 'height' => $new['height'], 'size' => $new['size'],
        ]);
        if ($row->path !== $new['path']) $media->delete($row->path);
        if ($row->thumb_path && $row->thumb_path !== $new['thumb_path']) $media->delete($row->thumb_path);
        $this->done++;
    }

    private function convertSingle(MediaService $media, string $table, string $col, object $row, int $side, string $pk): void
    {
        $old = $row->{$col};
        $src = $this->load('social', $old);
        if (! $src) { $this->failed++; return; }
        $new = $media->storeGd($src, $this->base($old), $side);
        $media->delete($new['thumb_path']);
        DB::connection('central')->table($table)->where($pk, $row->{$pk})->update([$col => $new['path']]);
        if ($old !== $new['path']) $media->delete($old);
        $this->done++;
    }

    private function convertPrivate(MediaService $media, object $row): void
    {
        $disk = Storage::disk('social_private');
        $src = $this->load('social_private', $row->path);
        if (! $src) { $this->failed++; return; }
        ob_start(); imagewebp($src, null, 85); $data = ob_get_clean(); imagedestroy($src);
        $path = $this->base($row->path) . '.webp';
        $disk->put($path, $data);
        DB::connection('central')->table('adhesion_documents')->where('id', $row->id)
            ->update(['path' => $path, 'mime' => 'image/webp', 'size' => strlen($data)]);
        if ($path !== $row->path) $disk->delete($row->path);
        $this->done++;
    }
}
