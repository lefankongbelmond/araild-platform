<?php

namespace App\Services\Social;

use App\Models\Central\PublicAccount;
use App\Models\Social\Upload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Vidéos et réels.
 *
 * Envoi PAR MORCEAUX (1 à 2 Mo) : fonctionne même si l'hébergement limite la
 * taille d'un envoi (upload_max_filesize) et reprend proprement sur une
 * connexion mobile lente. Le fichier assemblé est contrôlé sur son CONTENU
 * (signature MP4/MOV ou WebM, pas l'extension), sa taille et — pour le MP4 —
 * sa durée lue dans le fichier. Aucune conversion n'est faite sur le serveur
 * (l'hébergement mutualisé n'a pas d'outil vidéo) : la vidéo est publiée telle
 * qu'elle a été filmée ; l'image d'aperçu est préparée par le navigateur.
 */
class VideoService
{
    public const MIMES = ['video/mp4' => 'mp4', 'video/quicktime' => 'mov', 'video/webm' => 'webm'];

    public function __construct(private MediaService $media) {}

    public static function maxBytes(): int
    {
        return (int) config('mxconnect.social.video_max_mb', 100) * 1024 * 1024;
    }

    public static function chunkBytes(): int
    {
        return (int) config('mxconnect.social.video_chunk_kb', 1536) * 1024;
    }

    public function start(PublicAccount $account, int $size, string $mime, ?string $name): Upload
    {
        if (\App\Support\SocialLoad::light()) {
            throw ValidationException::withMessages(['video' => 'Le réseau est très sollicité en ce moment : l’envoi de vidéos est suspendu quelques minutes. Réessayez plus tard.']);
        }
        $this->cleanup();
        $mime = strtolower($mime);
        if (! isset(self::MIMES[$mime])) {
            throw ValidationException::withMessages(['video' => 'Format vidéo non accepté : utilisez une vidéo MP4, MOV ou WebM.']);
        }
        if ($size < 1 || $size > self::maxBytes()) {
            throw ValidationException::withMessages(['video' => 'Vidéo trop lourde : ' . config('mxconnect.social.video_max_mb', 100) . ' Mo maximum.']);
        }
        if (Upload::where('public_account_id', $account->id)->where('status', 'uploading')->where('created_at', '>', now()->subHour())->count() >= 3) {
            throw ValidationException::withMessages(['video' => 'Trop d’envois de vidéos en cours. Patientez quelques minutes.']);
        }
        File::ensureDirectoryExists(storage_path('app/uploads-tmp'));

        $upload = Upload::create([
            'id' => (string) Str::uuid(), 'public_account_id' => $account->id, 'mime' => $mime, 'size' => $size,
            'original_name' => $name ? Str::limit($name, 170, '') : null,
        ]);
        file_put_contents($upload->tempPath(), '');

        return $upload;
    }

    /** Ajoute un morceau à la position attendue. Renvoie le nombre d'octets reçus. */
    public function append(Upload $upload, int $offset, UploadedFile $chunk): int
    {
        if ($upload->status !== 'uploading') {
            throw ValidationException::withMessages(['video' => 'Cet envoi est terminé.']);
        }
        $path = $upload->tempPath();
        clearstatcache(true, $path);
        $have = is_file($path) ? filesize($path) : 0;
        if ($offset !== $have) {
            // Morceau déjà reçu (nouvel essai après coupure) ou décalé : le client reprend à $have.
            return $have;
        }
        $len = $chunk->getSize();
        if ($len < 1 || $len > self::chunkBytes() + 1024 || $have + $len > $upload->size) {
            throw ValidationException::withMessages(['video' => 'Morceau de vidéo invalide.']);
        }
        $in = fopen($chunk->getRealPath(), 'rb');
        $out = fopen($path, 'ab');
        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);
        clearstatcache(true, $path);
        $have = filesize($path);
        $upload->forceFill(['received' => $have, 'status' => $have >= $upload->size ? 'complete' : 'uploading'])->save();

        return $have;
    }

    /**
     * Contrôle la vidéo reçue et la range dans les médias publics.
     *
     * @return array{kind:string,path:string,thumb_path:?string,mime:string,width:?int,height:?int,duration:?int,size:int}
     */
    public function finalize(string $uploadId, PublicAccount $account, ?UploadedFile $poster, array $hints, bool $reel): array
    {
        $upload = Upload::where('id', $uploadId)->where('public_account_id', $account->id)->first();
        if (! $upload || $upload->status !== 'complete') {
            throw ValidationException::withMessages(['video' => 'La vidéo n’est pas encore entièrement envoyée.']);
        }
        $tmp = $upload->tempPath();
        clearstatcache(true, $tmp);
        if (! is_file($tmp) || filesize($tmp) !== (int) $upload->size) {
            throw ValidationException::withMessages(['video' => 'Vidéo incomplète : renvoyez-la.']);
        }

        $kind = $this->sniff($tmp);
        if (! $kind) {
            @unlink($tmp);
            $upload->update(['status' => 'used']);
            throw ValidationException::withMessages(['video' => 'Ce fichier n’est pas une vidéo MP4, MOV ou WebM valide.']);
        }
        $duration = $kind === 'webm' ? null : $this->mp4Duration($tmp);
        $duration ??= isset($hints['duration']) && is_numeric($hints['duration']) ? (int) round((float) $hints['duration']) : null;

        $maxReel = (int) config('mxconnect.social.reel_max_seconds', 90);
        $maxVideo = (int) config('mxconnect.social.video_max_seconds', 900);
        if ($reel && $duration && $duration > $maxReel + 1) {
            throw ValidationException::withMessages(['video' => "Un réel dure au maximum {$maxReel} secondes. Publiez-la plutôt comme vidéo."]);
        }
        if ($duration && $duration > $maxVideo + 1) {
            throw ValidationException::withMessages(['video' => 'Vidéo trop longue : ' . intdiv($maxVideo, 60) . ' minutes maximum.']);
        }

        $base = 'social/videos/' . now()->format('Y/m') . '/' . Str::uuid();
        $ext = $kind === 'webm' ? 'webm' : ($kind === 'mov' ? 'mov' : 'mp4');
        $dest = Storage::disk('social')->path("$base.$ext");
        File::ensureDirectoryExists(dirname($dest));
        if (! @rename($tmp, $dest)) {
            if (! @copy($tmp, $dest)) {
                throw ValidationException::withMessages(['video' => 'Enregistrement de la vidéo impossible.']);
            }
            @unlink($tmp);
        }
        $upload->update(['status' => 'used']);

        $thumb = null;
        if ($poster) {
            try {
                $thumb = $this->media->storeImage($poster, 'social/videos', 1280);
                Storage::disk('social')->delete($thumb['thumb_path']);
            } catch (\Throwable) {
                $thumb = null;   // aperçu facultatif : la vidéo reste publiable
            }
        }
        $w = isset($hints['width']) && ctype_digit((string) $hints['width']) ? min(8000, (int) $hints['width']) : ($thumb['width'] ?? null);
        $h = isset($hints['height']) && ctype_digit((string) $hints['height']) ? min(8000, (int) $hints['height']) : ($thumb['height'] ?? null);

        return [
            'kind' => 'video', 'path' => "$base.$ext", 'thumb_path' => $thumb['path'] ?? null,
            'mime' => $ext === 'webm' ? 'video/webm' : 'video/mp4',
            'width' => $w ?: null, 'height' => $h ?: null, 'duration' => $duration, 'size' => (int) $upload->size,
        ];
    }

    /** Signature réelle du fichier : 'mp4' | 'mov' | 'webm' | null. */
    public function sniff(string $path): ?string
    {
        $fh = fopen($path, 'rb');
        $head = fread($fh, 32) ?: '';
        fclose($fh);
        if (strlen($head) >= 12 && substr($head, 4, 4) === 'ftyp') {
            return substr($head, 8, 2) === 'qt' ? 'mov' : 'mp4';
        }
        if (str_starts_with($head, "\x1A\x45\xDF\xA3")) {
            return 'webm';
        }

        return null;
    }

    /** Durée (secondes) lue dans l'en-tête « mvhd » d'un MP4 / MOV, sans outil externe. */
    public function mp4Duration(string $path): ?int
    {
        $fh = fopen($path, 'rb');
        $size = filesize($path);
        try {
            $moov = $this->findAtom($fh, 0, $size, 'moov');
            if (! $moov) {
                return null;
            }
            $mvhd = $this->findAtom($fh, $moov[0], $moov[1], 'mvhd');
            if (! $mvhd) {
                return null;
            }
            fseek($fh, $mvhd[0]);
            $version = ord(fread($fh, 1));
            fread($fh, 3);
            if ($version === 1) {
                fread($fh, 16);
                $scale = unpack('N', fread($fh, 4))[1];
                $hi = unpack('N', fread($fh, 4))[1];
                $lo = unpack('N', fread($fh, 4))[1];
                $dur = $hi * 4294967296 + $lo;
            } else {
                fread($fh, 8);
                $scale = unpack('N', fread($fh, 4))[1];
                $dur = unpack('N', fread($fh, 4))[1];
            }

            return $scale > 0 ? (int) round($dur / $scale) : null;
        } catch (\Throwable) {
            return null;
        } finally {
            fclose($fh);
        }
    }

    /** @return array{0:int,1:int}|null [début du contenu, fin] de la boîte recherchée */
    private function findAtom($fh, int $start, int $end, string $type): ?array
    {
        $pos = $start;
        for ($i = 0; $i < 10000 && $pos + 8 <= $end; $i++) {
            fseek($fh, $pos);
            $hdr = fread($fh, 8);
            if (strlen($hdr) < 8) {
                return null;
            }
            $len = unpack('N', substr($hdr, 0, 4))[1];
            $name = substr($hdr, 4, 4);
            $headLen = 8;
            if ($len === 1) {
                $big = fread($fh, 8);
                $len = unpack('N', substr($big, 0, 4))[1] * 4294967296 + unpack('N', substr($big, 4, 4))[1];
                $headLen = 16;
            } elseif ($len === 0) {
                $len = $end - $pos;
            }
            if ($len < $headLen) {
                return null;
            }
            if ($name === $type) {
                return [$pos + $headLen, min($end, $pos + $len)];
            }
            $pos += $len;
        }

        return null;
    }

    /** Supprime les envois abandonnés (plus de 24 h). */
    public function cleanup(): void
    {
        Upload::where('created_at', '<', now()->subDay())->limit(50)->get()->each(function (Upload $u) {
            @unlink($u->tempPath());
            $u->delete();
        });
    }
}
