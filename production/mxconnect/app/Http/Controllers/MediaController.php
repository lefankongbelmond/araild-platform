<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Sert les images PUBLIQUES du réseau social (storage/app/public/social/...).
 *
 * Pourquoi une route plutôt que le lien symbolique public/storage : sur
 * l'hébergement mutualisé, le lien peut exister côté PHP mais être refusé ou
 * mal résolu par Apache (FollowSymLinks, chemin absolu différent) — les
 * photos publiées ne s'affichaient alors pas. Cette route ne dépend d'aucun lien.
 *
 * Hors groupe "web" : pas de session ni de cookie (réponses cachables).
 * Les noms de fichiers sont des UUID jamais réutilisés : cache "immutable".
 */
class MediaController extends Controller
{
    // Les .mov (iPhone) sont servis en video/mp4 : même conteneur, mieux lu par les navigateurs.
    private const TYPES = ['webp' => 'image/webp', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
                           'mp4' => 'video/mp4', 'mov' => 'video/mp4', 'webm' => 'video/webm'];

    public function __invoke(Request $request, string $path)
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        abort_unless(str_starts_with($path, 'social/') && isset(self::TYPES[$ext]), 404);

        $base = realpath(\Illuminate\Support\Facades\Storage::disk('social')->path(''));
        $file = $base ? realpath($base . DIRECTORY_SEPARATOR . $path) : false;
        abort_unless($file && str_starts_with($file, $base . DIRECTORY_SEPARATOR) && is_file($file), 404);

        $response = new BinaryFileResponse($file, 200, [
            'Content-Type'           => self::TYPES[$ext],
            'Cache-Control'          => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ], true, null, true, true);
        // Vidéos : lecture par plages (Range) gérée par BinaryFileResponse (avance rapide, iPhone).
        $response->headers->set('Accept-Ranges', 'bytes');
        $response->isNotModified($request);

        return $response;
    }
}
