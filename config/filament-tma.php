<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mode de l'installation
    |--------------------------------------------------------------------------
    |
    | « master » : l'application du centre de service (Filbreeze). Elle reçoit les tickets transmis, saisit les
    | temps, crée les tâches projet.
    | « client » : une application cliente (Desapp…). Ses tickets sont traités localement, ou transmis au maître.
    |
    */

    'mode' => env('TMA_MODE', 'client'),

    /*
    |--------------------------------------------------------------------------
    | Cette instance
    |--------------------------------------------------------------------------
    |
    | uid : identifiant global et stable de l'installation (un ULID, `php artisan tma:install` en propose un).
    | Il ne doit plus changer une fois des tickets créés : il est écrit dans chacun.
    | ticket_prefix : début des numéros lisibles des tickets créés ici (DES-0042).
    |
    */

    'instance' => [
        'uid' => env('TMA_INSTANCE_UID'),
        'name' => env('TMA_INSTANCE_NAME', env('APP_NAME', 'Application')),
        'ticket_prefix' => env('TMA_TICKET_PREFIX', 'TMA'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Le maître (mode client)
    |--------------------------------------------------------------------------
    |
    | Sans URL, le circuit « centre de service » est fermé : seuls les tickets locaux existent.
    | Le jeton n'est jamais versionné : il vient de .env.
    |
    */

    'master' => [
        'url' => env('TMA_MASTER_URL'),
        'token' => env('TMA_MASTER_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Modèles de l'application hôte
    |--------------------------------------------------------------------------
    |
    | user : le modèle des utilisateurs.
    | client : en mode maître, le modèle des organisations clientes (App\Models\Company dans Filbreeze).
    |
    */

    'models' => [
        'user' => env('TMA_USER_MODEL', 'App\\Models\\User'),
        'client' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Temps
    |--------------------------------------------------------------------------
    |
    | Les durées sont enregistrées en minutes. Une journée vaut `day_minutes` (7 h par défaut).
    |
    */

    'time' => [
        'day_minutes' => 420,
        'display_unit' => 'hours',
    ],

    /*
    |--------------------------------------------------------------------------
    | Clôture automatique
    |--------------------------------------------------------------------------
    |
    | Un ticket où la main est au demandeur (en attente de sa réponse, ou à valider) est clôturé après
    | `after_days` jours sans activité. Un avertissement part `warn_days_before` jours avant (null : aucun) ; un
    | ticket averti a toujours ce délai entier avant d'être clôturé. La commande passe chaque jour à `at`.
    |
    */

    'auto_close' => [
        'enabled' => true,
        'at' => '07:00',
        'after_days' => 28,
        'warn_days_before' => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pièces jointes
    |--------------------------------------------------------------------------
    |
    | Disque privé (jamais servi directement par le serveur web) ; liste blanche des types ; taille en Ko.
    |
    */

    'attachments' => [
        'disk' => env('TMA_ATTACHMENTS_DISK', 'local'),
        'max_size_kb' => 10240,
        'max_files' => 10,
        'accepted_types' => [
            'image/png',
            'image/jpeg',
            'image/gif',
            'image/webp',
            'application/pdf',
            'text/plain',
            'text/csv',
            'application/zip',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.oasis.opendocument.text',
            'application/vnd.oasis.opendocument.spreadsheet',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */

    'navigation' => [
        'group' => 'TMA',
        'sort' => null,
    ],

];
