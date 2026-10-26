<?php
/**
 * MX-CONNECT — point d'entrée pour hébergement mutualisé (racine web = htdocs/).
 *
 * L'application Laravel est cherchée dans :
 *   1. ./mxconnect    (paquet « tout dans htdocs », protégé par .htaccess)
 *   2. ../mxconnect   (variante : application hors de la racine web)
 */

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$appDir = null;
foreach ([__DIR__ . '/mxconnect', __DIR__ . '/../mxconnect'] as $candidate) {
    if (is_file($candidate . '/vendor/autoload.php')) {
        $appDir = realpath($candidate);
        break;
    }
}
if ($appDir === null) {
    http_response_code(500);
    exit('MX-CONNECT : dossier « mxconnect » introuvable (attendu à côté de htdocs/ ou dedans).');
}

if (file_exists($maintenance = $appDir . '/storage/framework/maintenance.php')) {
    require $maintenance;
}

/*
 | Mise à jour déposée par le gestionnaire de fichiers : au premier passage d'une
 | nouvelle version (fichier VERSION), on jette les caches compilés de l'ancienne
 | version (configuration, routes, vues) — sinon pages « 500 » — puis on applique
 | les migrations. Verrou fichier : une seule requête s'en charge.
 */
$version = @file_get_contents($appDir . '/VERSION');
$stamp = $appDir . '/bootstrap/cache/mx-version.txt';
$upgrade = false;
if ($version !== false && @file_get_contents($stamp) !== $version) {
    $lock = @fopen($appDir . '/bootstrap/cache/mx-upgrade.lock', 'c');
    if ($lock && flock($lock, LOCK_EX | LOCK_NB)) {
        if (@file_get_contents($stamp) !== $version) {
            foreach (['config.php', 'routes-v7.php', 'events.php'] as $f) {
                @unlink($appDir . '/bootstrap/cache/' . $f);
            }
            foreach (glob($appDir . '/storage/framework/views/*.php') ?: [] as $f) {
                @unlink($f);
            }
            if (function_exists('opcache_reset')) {
                @opcache_reset();
            }
            $upgrade = true;
        }
    }
}

require $appDir . '/vendor/autoload.php';

/** @var Application $app */
$app = require_once $appDir . '/bootstrap/app.php';
$app->usePublicPath(__DIR__);

if ($upgrade) {
    try {
        $app->make(Illuminate\Contracts\Console\Kernel::class)->call('mxconnect:post-update');
        @file_put_contents($stamp, $version);
    } catch (\Throwable $e) {
        // Base injoignable ou non installée : la page /_installation reste disponible.
        error_log('MX-CONNECT post-update : ' . $e->getMessage());
    }
    if (isset($lock) && $lock) {
        flock($lock, LOCK_UN);
    }
}

$app->handleRequest(Request::capture());
