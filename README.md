# filament-tma

Suivi de TMA et de tâches projet pour Filament 5, entre une application **maître** (le centre de service,
Filbreeze) et des applications **clientes** (Desapp…).

- Cahier des charges : [docs/CDC-v0.1.md](docs/CDC-v0.1.md)
- Décisions de conception, qui prévalent sur le CDC : [docs/ADDENDUM.md](docs/ADDENDUM.md)
- API entre les applications : [docs/API.md](docs/API.md)

**État : lots 1 et 2 terminés.**

- Lot 1 : tickets, workflow, chronologie, transmission au centre de service et synchronisation, temps passé,
  clôture automatique, commandes `tma:*`.
- Lot 2 : tâches projet, brouillons du maître et publication programmée, forfaits et seuils, récapitulatif
  quotidien et notifications.
- Reste le lot 3 : facturation vers les factures de Filbreeze, nettoyage des fichiers, audit complet.

## Installation

En local, comme les autres plugins `filament-*`, en dépôt `path` :

```json
{ "type": "path", "url": "../packages_filament/filament-tma", "options": { "symlink": true } }
```

```bash
composer require charlesstolive/filament-tma:@dev
php artisan vendor:publish --tag=filament-tma-config
php artisan vendor:publish --tag=filament-tma-migrations
php artisan migrate
php artisan filament:assets
```

Dans le panel :

```php
use CharlesStOlive\FilamentTma\FilamentTmaPlugin;

->plugins([FilamentTmaPlugin::make()])
```

Il faut aussi :

- **un planificateur** (`schedule:work`) : le plugin déclare ses tâches planifiées lui-même ;
- **un worker de file** dans le maître (`queue:work`) : les envois vers les clients passent par la file.

Dépendances : `charlesstolive/filament-state-fusion-enhanced` (les états), `filament/spatie-laravel-media-library-plugin`
(les fichiers), Passport (les jetons de l'API ; `filament-permission-manager` l'installe).
`charlesstolive/filament-permission-manager` est conseillé (les droits, voir plus bas).

### Variables d'environnement

| Variable | Rôle |
|---|---|
| `TMA_MODE` | `master` (centre de service) ou `client` (défaut) |
| `TMA_INSTANCE_UID` | identifiant global de l'installation, un ULID ; **ne change plus** une fois des tickets créés |
| `TMA_INSTANCE_NAME` | son nom affiché (défaut : `APP_NAME`) |
| `TMA_TICKET_PREFIX` | début des numéros des tickets créés ici : `DES` donne `DES-0042` |
| `TMA_TASK_PREFIX` | maître : début des numéros des tâches projet (`T` donne `T-0007`) |
| `TMA_MASTER_URL`, `TMA_MASTER_TOKEN`, `TMA_MASTER_API_USER` | mode client : le maître, et le compte dont il détient un jeton (voir [docs/API.md](docs/API.md)). Sans URL, seul le traitement local existe |
| `TMA_CLI_TOKEN` | le jeton personnel utilisé par les commandes `tma:*` |
| `TMA_ALLOWANCE_NOTIFY` | maître : adresses prévenues aux seuils des forfaits (séparées par des virgules) |
| `TMA_ATTACHMENTS_DISK` | disque **privé** des fichiers (défaut `local`) |

Un ULID : `php -r "require 'vendor/autoload.php'; echo Symfony\Component\Uid\Ulid::generate();"`.

Dans le maître, `config/filament-tma.php` déclare aussi le modèle des clients (`models.client`,
`App\Models\Company` dans Filbreeze, et `models.client_title`, `title`).

## Les tickets

Huit états (`States\Ticket`), et des **mouvements** (`Workflow\Moves`) qui les relient. Chaque mouvement dit quelle
partie peut le faire :

- le **demandeur**, c'est-à-dire l'application source ;
- l'**intervenant** : un collègue de l'application source en traitement local, le maître en centre de service.

Quand la main est à l'autre partie, on ne peut plus qu'ajouter des commentaires, des notes et des fichiers.

| Mouvement | De | Vers | Partie |
|---|---|---|---|
| Soumettre | Brouillon | Soumis | demandeur |
| Transmettre au centre de service | Brouillon (demandeur) ; Soumis, En cours, En attente (intervenant local) | Soumis, au centre de service | les deux |
| Publier chez le client | Brouillon préparé par le maître | Soumis | maître |
| Prendre en charge | Soumis | En cours | intervenant |
| Refuser (motif) | Soumis | Annulé | intervenant |
| Demander une réponse (question) | En cours | En attente du demandeur | intervenant |
| Répondre (réponse) | En attente du demandeur | En cours | demandeur |
| Mettre en pause (date) | En cours | En pause | intervenant |
| Reprendre | En pause, En attente du demandeur | En cours | intervenant |
| Soumettre à validation | En cours | À valider | intervenant |
| Valider | À valider | Clôturé | demandeur |
| Rouvrir (motif) | À valider | En cours | demandeur |
| Clôturer sans réponse | En attente du demandeur, À valider | Clôturé | intervenant |
| Annuler (motif) | tout état ouvert | Annulé | demandeur avant prise en charge, intervenant ensuite |
| Clôture automatique | En attente du demandeur, À valider | Clôturé | planificateur |
| Reprise à la date prévue | En pause | En cours | planificateur |
| Publication programmée | Brouillon préparé par le maître | Soumis | planificateur |

Tout passe par `Workflow\TicketWorkflow::run()`, quelle que soit l'origine : interface, API, commande ou
planificateur. Cette méthode :

- contrôle l'état, la partie et le droit ;
- valide les données ;
- écrit l'historique (`tma_state_transitions`) ;
- lance `Events\TicketTransitioned`.

Sur un ticket du centre de service, une application cliente ne fait pas le mouvement elle-même : elle le demande au
maître, puis recopie le résultat. Une application peut ajouter un mouvement :
`app(TicketWorkflow::class)->register(new MonMouvement)`.

**Brouillons du maître.** Le centre de service peut préparer un ticket pour un client, au nom de l'un de ses
utilisateurs (lus par l'API, `Sync\RemoteUsers`). Le ticket reste invisible chez le client jusqu'à sa publication,
immédiate ou programmée (`publish_at`).

## Les tâches projet

`Models\ProjectTask`, `Workflow\TaskWorkflow` (CDC §8) :

- elles sont créées dans le maître seulement, pour une application cliente ;
- leurs états : **Brouillon** (invisible du client), **En cours** (publiée), **Terminée**, **Annulée** ;
- **Publier** les met à disposition, tout de suite ou à `publish_at` ;
- le client les voit en lecture et participe par la chronologie : commentaires, notes, images, réponses.

## Ce que partagent tickets et tâches

Le contrat `Contracts\Trackable` (CDC §5) couvre :

- **la chronologie** (`Workflow\Timeline`) :
  - les commentaires, partagés avec l'autre partie ;
  - les notes, qui restent dans l'application où elles sont écrites jusqu'à leur partage ;
  - les réponses, sur un seul niveau ;
  - les résumés d'intervention ;
  - les fichiers joints à tout cela.

  Ajouter une entrée ne change jamais l'état.
- **le temps** (`Workflow\TimeLog`, maître seulement) : une saisie par intervention, en minutes, datée (elle se
  range dans son mois). La saisie se fait en minutes, heures ou jours (`time.day_minutes`, 7 h). Une saisie
  rattachée à une facture est verrouillée. Le client reçoit les totaux, global et par mois.
- **la synchronisation** :
  - du client vers le maître, l'appel est synchrone et annulé en cas d'échec ;
  - du maître vers le client, l'envoi passe par la file avec des tentatives (`Jobs\PushToInstance`).

Le HTML est nettoyé côté serveur à l'enregistrement (`Support\Html`) :
- ni script, ni attribut d'événement, ni iframe, ni SVG, ni `style` ;
- liens `http`, `https` et `mailto` uniquement.

Les fichiers sont stockés sur un disque privé. Leur type est contrôlé sur leur contenu réel, avec une liste
blanche. Ils ne se téléchargent que par `tma/media/{media}`, qui vérifie les droits à chaque fois.

## Les forfaits

`Models\Allowance`, écran « Forfaits » du maître (CDC §11) :

- **le volume** est défini par mois ou par trimestre, pour un client (partagé entre ses applications) ou pour une
  application ;
- **la consommation** est le temps facturable des tickets (`allowances.consume` peut y ajouter les tâches) ;
- **le suivi** affiche le restant, le pourcentage, le dépassement et le détail par mois ;
- **les seuils** sont à 80, 90, 100 et 110 % (`allowances.thresholds`). Chacun lance
  `Events\AllowanceThresholdReached` une fois par période (réinitialisable) et prévient `allowances.notify` ;
- **un dépassement** ne bloque pas la saisie, sauf avec `allowances.block_overage`.

Le client reçoit l'état de ses forfaits et l'affiche en tête de ses tickets (widget `AllowanceOverview`).

## Tâches planifiées et notifications

Le plugin déclare lui-même ses tâches planifiées :

| Commande | Quand | Rôle |
|---|---|---|
| `tma:close-stale` | chaque jour à `auto_close.at` (7 h) | Avertit le demandeur `warn_days_before` jours (7) avant de clôturer un ticket où la main est à lui et où rien ne s'est passé depuis `after_days` jours (28). Un ticket averti a toujours ce délai entier. Toute activité remet le compte à zéro. |
| `tma:resume-paused` | tous les quarts d'heure | Reprend les pauses arrivées à leur date. |
| `tma:publish-scheduled` | toutes les cinq minutes | Publie les tâches et les brouillons du maître arrivés à leur date. |
| `tma:digest` | jours ouvrés à 8 h (`notifications.digest`) | Envoie à chacun ce qui l'attend : réponses et validations, tickets à prendre, en cours, reprises du jour. Les retards sont signalés et personne ne reçoit de courriel vide. |

Les **notifications immédiates** sont désactivées par défaut (CDC §12.2) : tout passe par le récapitulatif.
`notifications.immediate` liste les mouvements qui préviennent tout de suite (`request-answer`,
`submit-for-validation`, `submit`, `take-charge`, `reopen`, `answer`, `publish`). La clôture automatique prévient
toujours.

**Chaque application prévient ses propres utilisateurs** (`Support\Notifier`). Un passage fait par le maître est
annoncé au demandeur par l'application cliente, à la réception.

## Les droits

Les droits suivent le format de `filament-permission-manager`, sans en dépendre (`permissions:sync` les crée) :

- **tickets** :
  - `tma.ticket.viewany`, `view`, `create`, `edit`, `delete` ;
  - `comment`, `note` (lire et écrire les notes internes), `share-note`, `time`, `sync` ;
  - la famille « Passages du ticket » : `tma.ticket.workflow.{mouvement}`.
- **tâches** : les mêmes en `tma.task.*`, avec la famille « Passages de la tâche ».
- **applications clientes** : `tma.instance.*`.
- **forfaits** : `tma.allowance.*`, plus `reset`.
- **widget du forfait côté client** : `widgets.allowanceoverview.viewany`.

Dans une application cliente, `tma.ticket.workflow.transmit` sépare les utilisateurs du centre de service des
utilisateurs « local uniquement ». Ces derniers n'apparaissent pas chez le maître.

Sans le gestionnaire, ces actions restent ouvertes à qui voit l'objet, comme dans les autres plugins. Qui a la main
compte toujours, quels que soient les droits.

## Les commandes `tma:*` (pour une IA)

Elles ne posent aucune question quand les options sont données. `--json` donne une sortie lisible par programme.

```bash
php artisan tma:list [--type=tickets|tasks|all] [--state=in_progress,awaiting_requester] [--app=Desapp] [--project=] [--search=] [--mine] [--all] [--json]
php artisan tma:show DES-0042|T-0007 [--json]
php artisan tma:log DES-0042 --hours=2 --summary="…" [--date=2026-10-07] [--non-billable] [--internal] [--json]
php artisan tma:log T-0007 --days=0.5 --summary-file=resume.md
php artisan tma:comment DES-0042 --body="…" [--note] [--json]
```

- **Jeton obligatoire** : `--token`, ou `TMA_CLI_TOKEN` dans `.env` (jamais versionné). C'est le jeton personnel
  Passport d'un utilisateur, et la commande a exactement ses droits.
- **Dans le maître**, la commande agit directement.
- **Dans une application cliente**, elle agit dans le maître par son API « agent », puisque le temps ne se saisit
  que là. Le jeton est alors celui d'un utilisateur du maître. `--local` la fait agir sur les tickets locaux de
  l'application, avec un jeton de l'application.
- `tma:log` enregistre séparément la durée (une saisie de temps) et le résumé (une entrée « Résumé
  d'intervention », partagée avec le client ; `--internal` en fait une note interne).

## Tests

Les tests vivent dans l'application hôte (Filbreeze : `tests/Feature/Tma`).
