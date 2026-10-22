<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mode de l'installation
    |--------------------------------------------------------------------------
    |
    | « master » : l'application du Support (Filbreeze). Elle reçoit les tickets transmis, saisit les temps,
    | crée les tâches projet.
    | « client » : une application cliente (Desapp…). Ses correspondants y écrivent leurs demandes et les transmettent
    | au maître, qui fait autorité dès lors (addendum §2).
    |
    */

    'mode' => env('TMA_MODE', 'client'),

    /*
    |--------------------------------------------------------------------------
    | Cette instance
    |--------------------------------------------------------------------------
    |
    | uid : identifiant global et stable de l'installation (un ULID ; `php artisan tma:install` le génère et l'écrit).
    | Il ne doit plus changer une fois des tickets créés : il est écrit dans chacun.
    | ticket_prefix : début des numéros lisibles des tickets créés ici (DES-0042) ; task_prefix : celui des tâches
    | projet, numérotées par le maître (T-0007).
    | short_name : mode client, le nom court du client (« EDM »). Il forme « Publier chez EDM », « Correspondants EDM » ;
    | le maître le règle dans la fiche de l'application, et `tma:install --short-name` l'écrit ici.
    |
    */

    'instance' => [
        'uid' => env('TMA_INSTANCE_UID'),
        'name' => env('TMA_INSTANCE_NAME', env('APP_NAME', 'Application')),
        'ticket_prefix' => env('TMA_TICKET_PREFIX', 'TMA'),
        'short_name' => env('TMA_INSTANCE_SHORT_NAME'),
        'task_prefix' => env('TMA_TASK_PREFIX', 'T'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Le maître (mode client)
    |--------------------------------------------------------------------------
    |
    | Sans URL, le circuit du Support est fermé : on ne fait que des brouillons.
    | Le jeton n'est jamais versionné : il vient de .env.
    |
    */

    'master' => [
        'url' => env('TMA_MASTER_URL'),
        'token' => env('TMA_MASTER_TOKEN'),
        // Le panneau du maître, tel que le navigateur le joint (les liens d'un membre du Support) ; vide : `url` + /admin.
        'panel_url' => env('TMA_MASTER_PANEL_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | API entre les applications
    |--------------------------------------------------------------------------
    |
    | guard : le guard des jetons (Passport, `api`).
    | master_user : mode client, l'utilisateur d'ici (id ou e-mail) dont le maître détient un jeton ; seul lui peut
    | appeler l'API du client. Côté maître, chaque application cliente est une instance (écran « Applications
    | clientes ») liée à l'utilisateur dont elle détient le jeton.
    | users_cache_ttl : le maître garde la liste des utilisateurs d'un client ce nombre de secondes.
    | sync : l'envoi du maître vers un client passe par la file, avec ces tentatives.
    |
    */

    'api' => [
        'guard' => env('TMA_API_GUARD', 'api'),
        // Mode maître : l'adresse à laquelle les applications clientes le joignent (http://filbreeze.test en développement).
        'public_url' => env('TMA_PUBLIC_URL'),
        'timeout' => 15,
        'master_user' => env('TMA_MASTER_API_USER'),
        'users_cache_ttl' => 3600,
        'sync' => [
            'queue' => env('TMA_SYNC_QUEUE'),
            'tries' => 6,
            'backoff' => [30, 120, 600, 1800, 3600],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Modèles de l'application hôte
    |--------------------------------------------------------------------------
    |
    | user : le modèle des utilisateurs.
    | client : en mode maître, le modèle des organisations clientes (App\Models\Company dans Filbreeze), et
    | client_title, la colonne qui le nomme (`title` pour Company).
    |
    */

    'models' => [
        'user' => env('TMA_USER_MODEL', 'App\\Models\\User'),
        'client' => null,
        'client_title' => 'name',
    ],

    /*
    |--------------------------------------------------------------------------
    | Temps
    |--------------------------------------------------------------------------
    |
    | Les durées sont enregistrées en minutes. Une journée vaut `day_minutes` (7 h par défaut).
    | display_unit : l'affichage des durées — `days_hours` (« 1 j 3 h », d'office), `hours` (« 10 h »), `days`
    | (« 1,43 j ») ou `minutes`.
    |
    */

    'time' => [
        'day_minutes' => 420,
        'display_unit' => 'days_hours',
    ],

    /*
    |--------------------------------------------------------------------------
    | Forfaits de TMA
    |--------------------------------------------------------------------------
    |
    | thresholds : les seuils (en % du volume) qui déclenchent un événement, une fois par période.
    | consume : ce qui consomme le forfait — le temps facturable des tickets ; `tasks` y ajoute celui des tâches projet.
    | block_overage : refuser une saisie facturable qui dépasserait 100 % (sinon, on alerte seulement).
    | notify : les adresses prévenues à chaque seuil (côté maître).
    |
    */

    'allowances' => [
        'thresholds' => [80, 90, 100, 110],
        'consume' => ['tickets'],
        'block_overage' => false,
        'notify' => array_filter(explode(',', (string) env('TMA_ALLOWANCE_NOTIFY', ''))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notes
    |--------------------------------------------------------------------------
    |
    | Une note commente un message, ou le ticket entier, à côté de l'échange ; tout le monde sur le ticket la lit.
    | Numérotée, elle s'archive quand on n'a plus besoin de la lire.
    | enabled : false, il n'y a plus que des messages.
    |
    */

    'notes' => [
        'enabled' => env('TMA_NOTES', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Clôture automatique
    |--------------------------------------------------------------------------
    |
    | Un ticket où la main est au demandeur (en attente de sa réponse, ou résolu) est clos après
    | `after_days` jours sans activité. Un avertissement part `warn_days_before` jours avant (null : aucun) ; un
    | ticket averti a toujours ce délai entier avant d'être clos. La commande passe chaque jour à `at`.
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
    | Rappels et notifications
    |--------------------------------------------------------------------------
    |
    | Qui est prévenu : les destinataires d'un ticket ou d'une tâche. Un ticket engage (on attend une action) ; une tâche
    | informe (on n'attend rien du client).
    | - Ticket : un principal est prévenu dans la cloche de tout ce qui s'y passe ; quand le ticket l'attend (création,
    |   ou la main lui revient), il reçoit aussi un courriel s'il a choisi l'e-mail direct, sinon c'est dans son bilan du
    |   matin. Les fins de ticket (clos, refusé, retiré) vont dans la cloche et le bilan ; une relance faite à la main
    |   part toujours par courriel.
    | - Tâche : chez le client, la cloche et le bilan de la semaine, jamais de courriel direct, sauf « Informer par
    |   courriel » (le Support, à la main). Le Support n'apprend d'une tâche que ce que le client y écrit.
    | - Un secondaire ne l'apprend que dans son bilan, en bas, sous « En copie » ; les autres, rien.
    | bell : la cloche de Filament (le panel doit avoir `->databaseNotifications()`) ; personne ne la coupe.
    | digest : le bilan du matin, ce qui attend chacun (CDC §12.1) puis ce qui a été mis de côté pour lui — heure, jours
    | (1 = lundi), fuseau, et `late_after_days` : au-delà, une attente est signalée en retard.
    | weekly : le bilan de la semaine des tâches projet, le jour `day` (1 = lundi) à `at`, dans le fuseau du bilan.
    | defaults : ce qui est actif pour qui n'a rien choisi — `mail` (l'e-mail direct des tickets), `digest` (le bilan du
    | matin), `weekly` (le bilan de la semaine). user_choice : chacun change ces choix pour lui (« Mes notifications »),
    | avec au moins l'e-mail direct ou le bilan du matin ; à false, `defaults` vaut pour tous.
    | stale : le rappel, à qui a la main, d'un ticket qui n'a pas changé d'état depuis `after_days` jours (ni brouillon,
    | ni en pause, ni terminé), ou d'une tâche en cours sans activité ; chaque matin à `at`, les jours du bilan, dans le
    | bilan, puis de nouveau tous les `after_days` jours tant que rien ne bouge.
    | keep_days : les notifications envoyées sont effacées au-delà (`model:prune`).
    |
    */

    'notifications' => [
        'bell' => true,
        'digest' => [
            'enabled' => true,
            'at' => '08:00',
            'days' => [1, 2, 3, 4, 5],
            'timezone' => env('TMA_TIMEZONE', env('APP_TIMEZONE', 'Europe/Paris')),
            'late_after_days' => 5,
        ],
        'weekly' => [
            'enabled' => true,
            'day' => 1,
            'at' => '08:00',
        ],
        'defaults' => ['digest', 'weekly'],
        'user_choice' => true,
        'stale' => [
            'enabled' => true,
            'after_days' => 7,
            'at' => '07:30',
        ],
        'keep_days' => 90,
    ],

    /*
    |--------------------------------------------------------------------------
    | Courriels
    |--------------------------------------------------------------------------
    |
    | logo : l'image en tête des courriels, un chemin sous public/ ou une adresse complète ; un PNG ou un JPEG (beaucoup
    | de messageries n'affichent pas le SVG). Sans fichier, le nom de l'application. Le logo du panel est d'habitude
    | public/images/logo.png.
    | colors : la couleur d'un ticket (`ticket`) et celle d'une tâche (`task`), en hexadécimal ; null : la couleur
    | primaire (ticket) et secondaire (tâche) de filament-ui, sinon un bleu et un gris ardoise.
    |
    */

    'mail' => [
        'logo' => env('TMA_MAIL_LOGO', 'images/logo.png'),
        'colors' => [
            'ticket' => null,
            'task' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Destinataires
    |--------------------------------------------------------------------------
    |
    | support_defaults (maître) : les adresses des membres du Support qui reçoivent ce qu'une application cliente
    | adresse « au Support » (son compte passerelle) ; vide : les membres du Support (`tma.ticket.handle`).
    | support_members (client) : les membres du Support reliés ici (agents) qu'on peut nommer un par un, en plus du
    | Support tout entier ; à réserver aux comptes Super Admin.
    |
    */

    'recipients' => [
        'support_defaults' => array_filter(explode(',', (string) env('TMA_SUPPORT_DEFAULTS', ''))),
        'support_members' => array_filter(explode(',', (string) env('TMA_SUPPORT_MEMBERS', ''))),
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
    | Audit et nettoyage
    |--------------------------------------------------------------------------
    |
    | audit : le journal des actions sensibles (CDC §23), écran « Journal d'audit ».
    | cleanup : `tma:cleanup` supprime définitivement, après `retention_days`, ce qui a été supprimé (tickets, tâches,
    | entrées) et les fichiers envoyés mais jamais rattachés ; jamais ce qui touche une saisie facturée. `schedule`
    | le lance chaque semaine (désactivé par défaut : à lancer d'abord avec --dry-run).
    |
    */

    'audit' => [
        'enabled' => true,
    ],

    'cleanup' => [
        'retention_days' => 90,
        'uploads_retention_hours' => 24,
        'schedule' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Correction orthographique par l'IA (facultative)
    |--------------------------------------------------------------------------
    |
    | Un bouton « Orthographe » sur le nouveau message et sur les formulaires des tickets et des tâches : la revue de
    | charlesstolive/filament-prism (rien ne part avant « Envoyer », on choisit ce qu'on applique). Il ne s'affiche
    | que si tout est là : filament-prism installé, la clé API de son fournisseur dans le .env (`OPENAI_API_KEY`…,
    | lue par config/prism.php), ce réglage allumé et, pour l'utilisateur, le droit `tma.ticket.ai.orthography`
    | (`tma.task.ai.orthography`). Sans clé, rien n'apparaît ; le droit se donne d'avance.
    | task : la tâche de filament-prism qui corrige.
    |
    */

    'ai' => [
        'orthography' => (bool) env('TMA_AI_ORTHOGRAPHY', true),
        'task' => 'orthography',
    ],

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */

    'navigation' => [
        'group' => 'Support',
        'sort' => null,
    ],

];
