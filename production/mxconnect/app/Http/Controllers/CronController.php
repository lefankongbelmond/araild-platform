<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/**
 * Tâches planifiées déclenchées par URL, pour les hébergements dont le panneau
 * ne permet que des « cron » d'appel web (ou un service externe type cron-job.org).
 *
 *   https://www.mx-connect.com/_cron?token=<CRON_TOKEN>   (toutes les 5 à 15 min)
 *
 * Exécute le planificateur Laravel puis vide la file d'attente (jobs en base).
 */
class CronController extends Controller
{
    public function __invoke(Request $request)
    {
        $expected = (string) config('mxconnect.cron_token');
        if (strlen($expected) < 24 || ! hash_equals($expected, (string) $request->query('token'))) {
            abort(404);
        }

        @set_time_limit(120);
        ignore_user_abort(true);

        $out = [];
        foreach ([
            ['schedule:run', []],
            ['queue:work', ['--stop-when-empty' => true, '--max-time' => 50, '--tries' => 3]],
            // Anciennes images (JPEG/PNG) converties en WebP par petits lots.
            ['mxconnect:media-webp', ['--seconds' => 15]],
        ] as [$cmd, $args]) {
            try {
                $code = Artisan::call($cmd, $args);
                $out[] = "[{$cmd}] " . ($code === 0 ? 'OK' : "code {$code}") . ' ' . trim(Artisan::output());
            } catch (\Throwable $e) {
                report($e);
                $out[] = "[{$cmd}] ERREUR " . $e->getMessage();
            }
        }

        return response(now()->toDateTimeString() . "\n" . implode("\n", $out) . "\n", 200)
            ->header('Content-Type', 'text/plain; charset=utf-8')
            ->header('Cache-Control', 'no-store')
            ->header('X-Robots-Tag', 'noindex');
    }
}
