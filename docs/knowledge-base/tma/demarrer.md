---
title: Configuration de départ
icon: heroicon-o-wrench-screwdriver
order: 20
---

# Configuration de départ

Cette page s'adresse à l'équipe technique : installer le module, puis relier une application cliente au Support.
Exemple : Filbreeze (le Support) et Desapp (application cliente).

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

## 2. Configurer l'application du Support

```bash
php artisan tma:install --mode=master --name=Filbreeze --prefix=FIL
```

La commande écrit dans `.env` le mode, le nom, le préfixe des numéros de tickets et un **identifiant** généré
(`TMA_INSTANCE_UID`). Cet identifiant est **définitif** : il est écrit dans chaque ticket, et la commande refuse de
le changer une fois des tickets créés.

Dans `config/filament-tma.php`, déclarez aussi le modèle des clients :
`'models' => ['client' => App\Models\Company::class, 'client_title' => 'title']`.

## 3. Déclarer l'application cliente (Support)

**Support › Applications clientes › Nouveau** : son nom, le **nom court** de son client (« EDM » : il forme
« Publier chez EDM », « Correspondants EDM », voir [Les mots du module](#modal-tma.comprendre.vocabulaire)), son adresse
(telle que le Support la joint) et son client. L'**identifiant** est proposé d'office (le bouton ↻ en génère un autre) : gardez-le. Si l'application a déjà
le sien (installée avant), collez-le à la place.

À l'enregistrement, le Support crée le **compte de service** de l'application : un utilisateur dédié,
sans rôle, qui n'entre dans aucun écran. On ne choisit jamais un compte existant.

## 4. Configuration de l'application

Sur la fiche, **Configuration de l'application** :

- **Adresse du Support** : celle que l'application cliente doit appeler (proposée d'après
  `TMA_PUBLIC_URL` ; en développement, celle du conteneur, pas `localhost`) ;
- **Préfixe des tickets** de l'application.

Une fenêtre montre la **commande à lancer dans l'application cliente**, avec un bouton pour la copier. Elle contient
un jeton qui n'est montré qu'une fois ; perdu, il suffit de relancer « Configuration de l'application ».

## 5. Lancer la commande dans l'application cliente

```bash
php artisan tma:install --mode=client --uid=01K… --name='Desapp' --short-name='EDM' --prefix='DES' \
    --master-url='https://filbreeze.example' --master-token=eyJ… --master-account=support@tma.local
```

Elle :

1. écrit la configuration dans le `.env` de l'application, dont le nom court du client ;
2. crée le compte par lequel le Support appellera l'application (`support@tma.local`, sans rôle) ;
3. **transmet son jeton au Support**, qui rappelle aussitôt l'application pour vérifier que la liaison
   marche dans les deux sens.

La commande dit ce qu'il en est. Sur la fiche, **Jeton de l'application** passe à « Reçu », et **Tester** vérifie la
connexion à tout moment.

Si la transmission échoue (Support injoignable), la commande **affiche le jeton** : collez-le dans la
fiche, champ **Saisir le jeton à la main**.

Sans configuration du Support, une application cliente ne fait que des brouillons : rien ne part.

## 6. Faire tourner les tâches de fond

- **Le planificateur** (`php artisan schedule:work`, ou la tâche cron de Laravel) : clôtures automatiques, reprises
  des pauses, publications programmées, récapitulatif quotidien. Voir
  [Rappels et notifications](#modal-tma.notifications).
- **Un worker de file** dans l'application du Support (`php artisan queue:work`) : les envois vers les applications
  clientes et les courriels passent par la file. Voir [La synchronisation](#modal-tma.synchronisation).

## 7. Donner les droits

```bash
php artisan permissions:sync --force
```

Puis, dans l'écran des rôles, donnez aux rôles concernés les permissions `tma.*`. Voir [Les droits](#modal-tma.droits).
Dans une application cliente, `tma.ticket.correspond` fait d'une personne un **correspondant du Support** ; chez le
Support, `tma.ticket.handle` fait d'une personne un **membre du Support**. Les forçages (`force-close`,
`force-return`) sont à réserver au Support.

## Les réglages courants (`config/filament-tma.php`)

| Réglage | Par défaut | Rôle |
| --- | --- | --- |
| `time.day_minutes` | 420 | durée d'une journée (7 h) |
| `time.display_unit` | `hours` | unité d'affichage du temps |
| `auto_close.after_days` | 28 | clôture d'un ticket sans réponse du demandeur |
| `auto_close.warn_days_before` | 7 | avertissement avant la clôture |
| `allowances.thresholds` | 80, 90, 100, 110 | seuils des forfaits, en % |
| `allowances.block_overage` | non | refuser une saisie qui dépasserait le forfait |
| `notifications.digest.at` | 08:00 | heure du bilan du matin (les tickets) |
| `notifications.weekly.day`, `.at` | lundi, 08:00 | jour et heure du bilan de la semaine (les tâches projet) |
| `notifications.defaults` | bilan du matin, bilan de la semaine | valeurs initiales de Mon profil › Notifications du Support (lien « Notifications › Mes notifications » dans les tickets) ; `mail` y ajoute l'e-mail direct |
| `mail.logo`, `mail.colors` | `images/logo.png`, couleurs de filament-ui | le logo et les couleurs des courriels |
| `notifications.stale.after_days` | 7 | rappel de ce qui ne bouge plus |
| `recipients.support_defaults` | — | maître : qui reçoit ce qu'un client adresse « au Support » |
| `attachments.accepted_types` | images, PDF, bureautique, CSV, ZIP | types de fichiers acceptés |
| `attachments.max_size_kb` | 10 240 | taille maximale d'un fichier |
| `cleanup.retention_days` | 90 | délai avant suppression définitive |

## En détail

- [L'identifiant d'une application](#modal-tma.demarrer.identifiant)
- [Les adresses des applications](#modal-tma.demarrer.adresse)
- [Les comptes de service et leurs jetons](#modal-tma.demarrer.jetons)
