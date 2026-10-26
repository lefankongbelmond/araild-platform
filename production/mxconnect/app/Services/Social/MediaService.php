<?php

namespace App\Services\Social;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Images du réseau social. Chaque image est DÉCODÉE puis RÉ-ENCODÉE avec GD
 * au format WebP (JPEG seulement si le serveur ne sait pas écrire le WebP) :
 *  - seuls de vrais JPEG/PNG/WebP/GIF passent (contrôle du contenu, pas de l'extension) ;
 *  - les métadonnées (EXIF, GPS) et tout contenu caché sont éliminés ;
 *  - redimensionnement (côté max configurable) + miniature pour le chargement différé.
 */
class MediaService
{
    private const ALLOWED = [IMAGETYPE_JPEG => 'image/jpeg', IMAGETYPE_PNG => 'image/png', IMAGETYPE_WEBP => 'image/webp', IMAGETYPE_GIF => 'image/gif'];

    /** URL publique d'une image stockée sur le disque "social" (servie par /media). */
    public static function url(?string $path): ?string
    {
        return $path ? '/media/' . ltrim($path, '/') : null;
    }

    /** WebP si GD sait l'écrire sur ce serveur, sinon JPEG (repli honnête). */
    public static function webpSupported(): bool
    {
        return function_exists('imagewebp') && (imagetypes() & IMG_WEBP);
    }

    /** @return array{path:string,thumb_path:string,mime:string,width:int,height:int,size:int} */
    public function storeImage(UploadedFile $file, string $folder, ?int $maxSide = null): array
    {
        $cfg = config('mxconnect.social');
        if ($file->getSize() > $cfg['max_upload_kb'] * 1024) {
            throw ValidationException::withMessages(['images' => 'Image trop lourde (maximum ' . round($cfg['max_upload_kb'] / 1024) . ' Mo).']);
        }

        $src = $this->decode($file->getRealPath(), 'images', 'Image illisible ou corrompue.');
        $base = trim($folder, '/') . '/' . now()->format('Y/m') . '/' . Str::uuid();

        return $this->storeGd($src, $base, $maxSide ?? $cfg['image_max_side']);
    }

    /**
     * Enregistre une image GD déjà décodée (principale + miniature) au format WebP.
     * Libère $src. Utilisé aussi par la conversion des anciennes images.
     *
     * @return array{path:string,thumb_path:string,mime:string,width:int,height:int,size:int}
     */
    public function storeGd(\GdImage $src, string $base, int $maxSide): array
    {
        $cfg = config('mxconnect.social');
        $q = (int) ($cfg['webp_quality'] ?? 80);
        $main = $this->encode($src, $maxSide, $q);
        $thumb = $this->encode($src, $cfg['thumb_max_side'], max(50, $q - 8));
        imagedestroy($src);

        $ext = $main['ext'];
        Storage::disk('social')->put("$base.$ext", $main['data']);
        Storage::disk('social')->put("{$base}_t.$ext", $thumb['data']);

        return [
            'path' => "$base.$ext", 'thumb_path' => "{$base}_t.$ext", 'mime' => $main['mime'],
            'width' => $main['w'], 'height' => $main['h'], 'size' => strlen($main['data']),
        ];
    }

    /**
     * Réencode une image en WebP (ou JPEG en repli) sans métadonnées, pour un
     * stockage PRIVÉ (pièces d'un dossier d'adhésion).
     *
     * @return array{data:string,ext:string,mime:string}
     */
    public function privateImage(UploadedFile $file, int $maxSide = 2000): array
    {
        $src = $this->decode($file->getRealPath(), 'documents', 'Image illisible : ' . $file->getClientOriginalName());
        $out = $this->encode($src, $maxSide, 85);
        imagedestroy($src);

        return ['data' => $out['data'], 'ext' => $out['ext'], 'mime' => $out['mime']];
    }

    /** Décode un fichier image (contrôle du contenu réel, pas de l'extension). */
    public function decode(string $path, string $field = 'images', string $error = 'Image illisible ou corrompue.'): \GdImage
    {
        $info = @getimagesize($path);
        if (! $info || ! isset(self::ALLOWED[$info[2]])) {
            throw ValidationException::withMessages([$field => 'Format non accepté : seules les images JPEG, PNG, WebP ou GIF sont autorisées.']);
        }
        $src = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG  => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            IMAGETYPE_GIF  => @imagecreatefromgif($path),
        };
        if (! $src) {
            throw ValidationException::withMessages([$field => $error]);
        }

        return $this->orient($src, $path, $info[2]);
    }

    public function delete(?string ...$paths): void
    {
        foreach (array_filter($paths) as $p) {
            Storage::disk('social')->delete($p);
        }
    }

    /** @return array{data:string,w:int,h:int,ext:string,mime:string} */
    private function encode(\GdImage $src, int $maxSide, int $quality): array
    {
        if (! imageistruecolor($src)) {
            imagepalettetotruecolor($src); // GIF / PNG à palette
        }
        $w = imagesx($src); $h = imagesy($src);
        $ratio = min(1, $maxSide / max($w, $h));
        $nw = max(1, (int) round($w * $ratio)); $nh = max(1, (int) round($h * $ratio));
        $webp = self::webpSupported();

        $dst = imagecreatetruecolor($nw, $nh);
        if ($webp) {
            // WebP garde la transparence (logos PNG).
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 255, 255, 255, 127));
        } else {
            imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // fond blanc (JPEG)
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        ob_start();
        $webp ? imagewebp($dst, null, $quality) : imagejpeg($dst, null, $quality);
        $data = ob_get_clean();
        imagedestroy($dst);

        return ['data' => $data, 'w' => $nw, 'h' => $nh, 'ext' => $webp ? 'webp' : 'jpg', 'mime' => $webp ? 'image/webp' : 'image/jpeg'];
    }

    /** Redresse les photos de téléphone (orientation EXIF) avant d'effacer les métadonnées. */
    private function orient(\GdImage $img, string $path, int $type): \GdImage
    {
        if ($type !== IMAGETYPE_JPEG || ! function_exists('exif_read_data')) {
            return $img;
        }
        $o = (int) (@exif_read_data($path)['Orientation'] ?? 1);

        return match ($o) {
            3 => imagerotate($img, 180, 0),
            6 => imagerotate($img, -90, 0),
            8 => imagerotate($img, 90, 0),
            default => $img,
        };
    }
}
