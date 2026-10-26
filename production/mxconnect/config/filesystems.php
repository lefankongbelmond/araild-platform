<?php

use Illuminate\Support\Str;

return [

    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            // Désactivé : non utilisé, et sa route /storage/{path} masquait le
            // repli qui sert les fichiers publics quand les liens symboliques
            // sont interdits (hébergement mutualisé).
            'serve' => false,
            'throw' => false,
        ],

        // Médias du réseau social : même dossier que « public » (storage/app/public)
        // mais JAMAIS redirigé par stancl/tenancy (non listé dans tenancy.filesystem.disks),
        // pour qu'une image publiée depuis l'espace d'une mutuelle reste visible partout.
        // Pièces des dossiers d'adhésion : PRIVÉ (jamais sous /storage public),
        // lu uniquement par un contrôleur qui contrôle les droits.
        'social_private' => [
            'driver' => 'local',
            'root' => storage_path('app/private-social'),
            'visibility' => 'private',
            'throw' => false,
        ],

        'social' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'visibility' => 'public',
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        // Off-server encrypted backups (spatie/laravel-backup) — see DEPLOIEMENT_LWS.md §6.
        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],

    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
