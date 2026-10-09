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

## 2. Configurer le centre de service

```bash
php artisan tma:install --mode=master --name=Filbreeze --prefix=FIL
```

La commande écrit dans `.env` le mode, le nom, le préfixe des numéros de tickets et un **identifiant** généré
(`TMA_INSTANCE_UID`). Cet identifiant est **définitif** : il est écrit dans chaque ticket, et la commande refuse de
le changer une fois des tickets créés.

Dans `config/filament-tma.php`, déclarez aussi le modèle des clients :
`'models' => ['client' => App\Models\Company::class, 'client_title' => 'title']`.

## 3. Déclarer l'application cliente (centre de service)

**TMA › Applications clientes › Nouveau** : son nom, son adresse et son client. L'**identifiant** est proposé
d'office (le bouton ↻ en génère un autre) : gardez-le. Si l'application a déjà le sien, collez-le à la place.

Puis **Configuration de l'application** (dans la liste, ou en tête de la fiche) :

- le **compte de service** par lequel l'application appellera le centre de service est créé (sans rôle : il n'entre
  dans aucun écran), et reçoit un jeton ; un nouveau jeton remplace l'ancien ;
- l'**adresse du centre de service** est celle que l'application cliente doit appeler (en développement, celle du
  conteneur, pas `localhost`) ;
- une fenêtre montre la **commande à lancer dans l'application cliente**, avec un bouton pour la copier.

Ce jeton n'est montré qu'une fois. Perdu, il suffit de relancer « Configuration de l'application ».

## 4. Configurer l'application cliente

Lancez-y la commande copiée. Elle ressemble à :

```bash
php artisan tma:install --mode=client --uid=01K… --name='Desapp' --prefix='DES' \
    --master-url='https://filbreeze.example' --master-token=eyJ… --master-account=centre-de-service@tma.local
```

Elle écrit la configuration dans `.env`, puis crée le compte par lequel le **centre de service** appellera
l'application (`centre-de-service@tma.local`, sans rôle), et **affiche son jeton**.

## 5. Terminer dans le centre de service

Sur la fiche de l'application cliente, collez ce jeton dans **Jeton émis par l'application**, enregistrez, puis
**Tester** : la connexion est vérifiée dans les deux sens. **Utilisateurs** montre qui, dans l'application cliente,
a accès au centre de service.

Sans configuration du centre de service, une application cliente ne fait que du traitement local.

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
