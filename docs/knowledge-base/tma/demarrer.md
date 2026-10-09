---
title: Configuration de départ
icon: heroicon-o-wrench-screwdriver
order: 20
---

# Configuration de départ

Cette page s'adresse à l'équipe technique : installer le module, puis relier une application cliente au centre de
service. Exemple : Filbreeze (centre de service) et Desapp (application cliente).

## 1. Installer le module dans chaque application

```bash
composer require charlesstolive/filament-tma
php artisan vendor:publish --tag=filament-tma-config
php artisan vendor:publish --tag=filament-tma-migrations
php artisan vendor:publish --tag=filament-tma-docs
php artisan migrate
php artisan filament:assets
```

Dans le panel d'administration : `->plugins([FilamentTmaPlugin::make()])`.

Le thème Filament de l'application doit lire les vues du module et de la base de connaissances :

```css
@plugin "@tailwindcss/typography";
@source '../../../../vendor/charlesstolive/filament-tma/resources/**/*.blade.php';
@source '../../../../vendor/guava/filament-knowledge-base/src/**/*';
@source '../../../../vendor/guava/filament-knowledge-base/resources/views/**/*';
```

Puis `npm run build`.

## 2. Régler chaque application (`.env`)

| Variable | Centre de service | Application cliente |
| --- | --- | --- |
| `TMA_MODE` | `master` | `client` |
| `TMA_INSTANCE_UID` | un ULID propre à l'application | un ULID propre à l'application |
| `TMA_INSTANCE_NAME` | `Filbreeze` | `Desapp` |
| `TMA_TICKET_PREFIX` | `FIL` | `DES` |
| `TMA_TASK_PREFIX` | `T` | — |
| `TMA_MASTER_URL` | — | l'adresse du centre de service |
| `TMA_MASTER_TOKEN` | — | le jeton émis par le centre de service (étape 4) |
| `TMA_MASTER_API_USER` | — | l'e-mail du compte de service créé ici (étape 3) |

L'identifiant (`TMA_INSTANCE_UID`) est **définitif** : il est écrit dans chaque ticket. Pour en obtenir un :
`php -r "require 'vendor/autoload.php'; echo Symfony\Component\Uid\Ulid::generate();"`.

Sans `TMA_MASTER_URL`, une application cliente ne fait que du traitement local.

Dans le centre de service, `config/filament-tma.php` déclare aussi le modèle des clients :
`'models' => ['client' => App\Models\Company::class, 'client_title' => 'title']`.

## 3. Dans l'application cliente : le compte du centre de service

Créez un utilisateur de service (par exemple `filbreeze@api.local`), sans rôle, et émettez-lui un **jeton API**.
C'est par ce jeton que le centre de service appellera l'application. Son e-mail va dans `TMA_MASTER_API_USER`.

## 4. Dans le centre de service : le compte de l'application cliente

Créez un utilisateur de service (par exemple `desapp@api.local`) et émettez-lui un **jeton API**. Ce jeton va dans
`TMA_MASTER_TOKEN` de l'application cliente.

## 5. Dans le centre de service : déclarer l'application

**TMA › Applications clientes › Nouveau** :

- **Identifiant** : le `TMA_INSTANCE_UID` de l'application cliente ;
- **Adresse** : son URL ;
- **Client** : la société ;
- **Compte de service** : l'utilisateur de l'étape 4 ;
- **Jeton** : celui de l'étape 3.

Le bouton **Tester** vérifie la connexion ; **Utilisateurs** montre qui, dans l'application cliente, a accès au
centre de service.

## 6. Faire tourner les tâches de fond

- **Le planificateur** (`php artisan schedule:work`, ou la tâche cron de Laravel) : clôtures automatiques, reprises
  des pauses, publications programmées, récapitulatif quotidien. Voir
  [Rappels et notifications](#modal-tma.notifications).
- **Un worker de file** dans le centre de service (`php artisan queue:work`) : les envois vers les applications
  clientes et les courriels passent par la file. Voir [La synchronisation](#modal-tma.synchronisation).

## 7. Donner les droits

```bash
php artisan permissions:sync --force
```

Puis, dans l'écran des rôles, donnez aux rôles concernés les permissions `tma.*`. Voir [Les droits](#modal-tma.droits).
Dans une application cliente, `tma.ticket.workflow.transmit` distingue les utilisateurs qui ont accès au centre de
service de ceux qui n'ont que le traitement local.

## Les réglages courants (`config/filament-tma.php`)

| Réglage | Par défaut | Rôle |
| --- | --- | --- |
| `time.day_minutes` | 420 | durée d'une journée (7 h) |
| `time.display_unit` | `hours` | unité d'affichage du temps |
| `auto_close.after_days` | 28 | clôture d'un ticket sans réponse du demandeur |
| `auto_close.warn_days_before` | 7 | avertissement avant la clôture |
| `allowances.thresholds` | 80, 90, 100, 110 | seuils des forfaits, en % |
| `allowances.block_overage` | non | refuser une saisie qui dépasserait le forfait |
| `notifications.digest.at` | 08:00 | heure du récapitulatif quotidien |
| `notifications.immediate` | aucun | passages qui préviennent tout de suite |
| `attachments.accepted_types` | images, PDF, bureautique, CSV, ZIP | types de fichiers acceptés |
| `attachments.max_size_kb` | 10 240 | taille maximale d'un fichier |
| `cleanup.retention_days` | 90 | délai avant suppression définitive |
