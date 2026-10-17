# filament-tma

Suivi de TMA et de tâches projet pour Filament 5, entre une application **maître** (le centre de service,
Filbreeze) et des applications **clientes** (Desapp…).

- Cahier des charges : [docs/CDC-v0.1.md](docs/CDC-v0.1.md)
- Décisions de conception, qui prévalent sur le CDC : [docs/ADDENDUM.md](docs/ADDENDUM.md)
- API entre les applications : [docs/API.md](docs/API.md)

**État : lots 1 et 2 terminés ; lot 3 : audit et nettoyage faits, facturation à décider.**

- Lot 1 : tickets, workflow, chronologie, transmission au centre de service et synchronisation, temps passé,
  clôture automatique, commandes `tma:*`.
- Lot 2 : tâches projet, brouillons du maître et publication programmée, forfaits et seuils, récapitulatif
  quotidien et notifications.
- Lot 3 : journal d'audit et nettoyage. Reste la facturation vers les factures de Filbreeze, à discuter.

## Installation

En local, comme les autres plugins `filament-*`, en dépôt `path` :

```json
{ "type": "path", "url": "../packages_filament/filament-tma", "options": { "symlink": true } }
```

```bash
composer require charlesstolive/filament-tma:@dev
php artisan vendor:publish --tag=filament-tma-config
php artisan vendor:publish --tag=filament-tma-migrations
php artisan vendor:publish --tag=filament-tma-docs
php artisan migrate
php artisan filament:assets
```

Dans le panel :

```php
use CharlesStOlive\FilamentTma\FilamentTmaPlugin;

->plugins([FilamentTmaPlugin::make()])
```

## L'aide intégrée (base de connaissances)

Le plugin embarque son aide pour `guava/filament-knowledge-base` : 13 pages dans `docs/knowledge-base/tma/` (groupe
« TMA et tâches projet »). Elles couvrent la compréhension du module, la configuration de départ, les tickets, la
chronologie, le temps, les tâches, les forfaits, les notifications, la synchronisation, les droits, les commandes,
l'audit et un glossaire.

- `vendor:publish --tag=filament-tma-docs` les copie dans `docs/knowledge-base/fr` de l'application. Ajoutez-le au
  `post-autoload-dump` de `composer.json` (avec `--force`) pour qu'elles suivent les mises à jour.
- Chaque écran du plugin a son menu « ? » (`HasKnowledgeBase::getDocumentation()`), si l'application a un panneau
  de base de connaissances et le `KnowledgeBaseCompanionPlugin` dans son admin.
- Le thème Filament de l'application lit les vues du plugin et de la base de connaissances :
  `@plugin "@tailwindcss/typography";` et les `@source` de `vendor/charlesstolive/filament-tma/resources` et
  `vendor/guava/filament-knowledge-base` (voir la page « Configuration de départ »).

Il faut aussi :

- **un planificateur** (`schedule:work`) : le plugin déclare ses tâches planifiées lui-même ;
- **un worker de file** dans le maître (`queue:work`) : les envois vers les clients passent par la file.

Dépendances : `charlesstolive/filament-state-fusion-enhanced` (les états), `filament/spatie-laravel-media-library-plugin`
(les fichiers), `guava/filament-knowledge-base` (l'aide), Passport (les jetons de l'API ; `filament-permission-manager` l'installe).
`charlesstolive/filament-permission-manager` est conseillé (les droits, voir plus bas).

### Variables d'environnement

| Variable | Rôle |
|---|---|
| `TMA_MODE` | `master` (centre de service) ou `client` (défaut) |
| `TMA_INSTANCE_UID` | identifiant global de l'installation, un ULID ; **ne change plus** une fois des tickets créés |
| `TMA_INSTANCE_NAME` | son nom affiché (défaut : `APP_NAME`) |
| `TMA_TICKET_PREFIX` | début des numéros des tickets créés ici : `DES` donne `DES-0042` |
| `TMA_TASK_PREFIX` | maître : début des numéros des tâches projet (`T` donne `T-0007`) |
| `TMA_MASTER_URL`, `TMA_MASTER_TOKEN`, `TMA_MASTER_API_USER` | mode client : le maître, et le compte dont il détient un jeton (voir [docs/API.md](docs/API.md)). Sans URL, on ne fait que des brouillons |
| `TMA_PUBLIC_URL` | maître : son adresse vue des applications clientes (`http://filbreeze.test` en développement) |
| `TMA_CLI_TOKEN` | le jeton personnel utilisé par les commandes `tma:*` |
| `TMA_ALLOWANCE_NOTIFY` | maître : adresses prévenues aux seuils des forfaits (séparées par des virgules) |
| `TMA_ATTACHMENTS_DISK` | disque **privé** des fichiers (défaut `local`) |
| `TMA_AI_ORTHOGRAPHY` | `false` éteint la correction orthographique par l'IA (défaut `true` ; sans clé API, elle ne s'affiche pas de toute façon) |

`php artisan tma:install` écrit tout cela dans `.env` et génère l'identifiant ; dans le maître, « Applications
clientes › Configuration de l'application » donne la commande complète à lancer dans une application cliente.

Dans le maître, `config/filament-tma.php` déclare aussi le modèle des clients (`models.client`,
`App\Models\Company` dans Filbreeze, et `models.client_title`, `title`).

## Les tickets

Un ticket va toujours d'une application cliente au Support (le maître), qui fait autorité dès qu'il quitte le
brouillon (addendum §2). Huit états (`States\Ticket`), et des **mouvements** (`Workflow\Moves`) qui les relient.

**La règle du tour** (addendum §3) : chaque état dit à qui est le tour ; dans le circuit normal, seule cette partie
agit, et du côté du client, seulement le demandeur lui-même (`Move::allowsRequester()`). Les passages normaux ne sont
pas des droits : aucun droit, pas même Super Admin, ne permet d'agir hors de son tour. Les **forçages**
(`Move::override()`) ne dépendent que de leur droit.

| Mouvement | De | Vers | Qui |
|---|---|---|---|
| Transmettre au Support (contact au Support : d'office s'il n'y en a qu'un) | Brouillon | Nouveau, chez ce contact | le demandeur (correspondant) |
| Retirer la demande (motif, sauf brouillon) | Brouillon, Nouveau | Annulé | le demandeur |
| Publier chez le client | Brouillon préparé par le maître | Nouveau | le Support |
| Refuser (motif) | Nouveau | Annulé | le Support |
| Demander des précisions (question) | Nouveau, En cours | En attente du demandeur | le Support |
| Marquer comme résolu | Nouveau, En cours | Résolu | le Support |
| Mettre en pause (date) | Nouveau, En cours | En pause | le Support |
| Reprendre | En pause | En cours | le Support |
| Répondre (réponse) | En attente du demandeur | En cours | le demandeur |
| Confirmer et clore (mot facultatif) | Résolu | Clos | le demandeur |
| Retour au Support (motif) | Résolu | En cours | le demandeur |
| Prendre la main | En attente du demandeur, Résolu | (inchangé) : il devient le demandeur | un autre destinataire principal, de la même application |
| Forcer la clôture (motif) | tout état ouvert après le brouillon | Clos | droit `force-close` |
| Forcer le retour | En attente du demandeur, Résolu | En cours | droit `force-return` (Support) |
| Clôture automatique | En attente du demandeur, Résolu | Clos | planificateur |
| Reprise à la date prévue | En pause | En cours | planificateur |
| Publication programmée | Brouillon préparé par le maître | Nouveau | planificateur |

Pas de « prise en charge » : le contact au Support est choisi à la transmission, et le Support répond dès « Nouveau ».

**Messages** : un message va toujours avec un passage ; il n'y a pas de message à part. Un point d'étape, une
précision : une note ; on peut aussi relancer (`remind`). Sur un ticket, le temps n'a pas de texte. Un message ne se modifie que s'il est le
dernier, par son auteur ; la demande, par son auteur, tant qu'aucun message ne l'a suivie (`PATCH tickets/{ulid}`
depuis le client).

Tout passe par `Workflow\TicketWorkflow::run()`, quelle que soit l'origine : interface, API, commande ou
planificateur. Cette méthode :

- contrôle l'état, la partie, la personne (le tour) ou le droit d'un forçage ;
- valide les données ;
- écrit l'historique (`tma_state_transitions`) ;
- lance `Events\TicketTransitioned`.

Sur un ticket transmis, une application cliente ne fait pas le mouvement elle-même : elle le demande au maître, puis
recopie le résultat. Une application peut ajouter un mouvement :
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
  - les notes, lues de tous : sur un message ou sur le ticket entier, numérotées, archivables ;
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

### La correction orthographique (facultative)

Un bouton **Orthographe** relit par l'IA le nouveau message (`HasComposer`) et le titre et la description des
formulaires de ticket et de tâche (`Filament\Concerns\HasOrthography`). Il passe par
`charlesstolive/filament-prism`, que le plugin ne demande pas (`suggest`) : `CorrectionAction`, avec la revue de
prism (brouillon, « Envoyer », corrections à appliquer). « Appliquer » remet le texte dans le champ, comme une frappe ;
on l'envoie ou on l'enregistre ensuite.

Le bouton ne s'affiche que si tout est là (`Support\Orthography::available()`) :

- filament-prism installé et migré, avec sa tâche `orthography` (`filament-tma.ai.task`) ;
- la clé API de son fournisseur (`prism.providers.<fournisseur>.api_key`, `OPENAI_API_KEY`…). Sans clé, rien ne
  s'affiche : une application cliente sans IA ne voit rien ;
- `filament-tma.ai.orthography` allumé (`TMA_AI_ORTHOGRAPHY`, défaut `true`) ;
- le droit `tma.ticket.ai.orthography` (`tma.task.ai.orthography`), vérifié par prism (`AiPermissions`). Il est déclaré
  dès que prism est installé, même sans clé : on le donne d'avance.

La demande se rattache au ticket ou à la tâche, ou à l'utilisateur sur une page de création, avec la clé
`tma:{tickets|tasks}:{composer|form}`. À l'application, la page relit le texte dans la demande en base, et seulement
si elle est de l'utilisateur connecté.

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

## Jeu d'exemples

`tma:demo` vide les données de travail du module dans l'application où on la lance (tickets, tâches, projets,
messages, notes, temps et leurs fichiers ; restent les applications reliées, les membres du Support, les forfaits et
les utilisateurs) et rejoue des cas par le vrai workflow : les deux applications se synchronisent comme en vrai. Pour
repartir de zéro, dans l'ordre :

```bash
# application cliente : vider
php artisan tma:demo --wipe --force
# maître : vider, puis projets, tâches (terminée, en cours, en retard, programmée, annulée), temps, tickets préparés
php artisan tma:demo --fresh --force
# application cliente : demandes au Support dans tous les états, traitées par le membre du Support relié (nouvelle,
# retirée, question, reprise par un autre destinataire, en cours avec notes et temps, en pause, résolue, close,
# close d'office, partie d'une tâche), messages et notes sur les tâches
php artisan tma:demo --force
```

Le jeu du client prend ses vrais utilisateurs : les correspondants du Support (`tma.ticket.correspond`) et le membre
du Support relié ici. Les histoires sont datées dans le passé, à l'heure du narrateur. Jamais en
production sans `--force`.

## Remettre le schéma à plat

Le plugin a un seul jeu de migrations (addendum §3 bis). Dans une installation qui a passé les anciennes : retirer de
`database/migrations` les anciennes migrations `*tma*`, publier les nouvelles
(`vendor:publish --tag=filament-tma-migrations`), puis, **avant** `migrate` :

```bash
php artisan tma:reset-schema --force   # garde les applications reliées, les membres du Support, les forfaits
php artisan tma:reset-schema --all --force   # vide tout
php artisan permissions:sync --force   # puis donner correspond / handle / les forçages aux rôles
```

Tickets, tâches, messages, notes et temps sont perdus.

## Tâches planifiées et notifications

Le plugin déclare lui-même ses tâches planifiées :

| Commande | Quand | Rôle |
|---|---|---|
| `tma:close-stale` | chaque jour à `auto_close.at` (7 h) | Avertit le demandeur `warn_days_before` jours (7) avant de clôturer un ticket où la main est à lui et où rien ne s'est passé depuis `after_days` jours (28). Un ticket averti a toujours ce délai entier. Toute activité remet le compte à zéro. |
| `tma:resume-paused` | tous les quarts d'heure | Reprend les pauses arrivées à leur date. |
| `tma:publish-scheduled` | chaque minute | Publie les tâches et les brouillons du maître arrivés à leur date. |
| `tma:notify --stale` | jours du récapitulatif à `notifications.stale.at` (7 h 30) | Rappelle à qui a la main un ticket qui n'a pas changé d'état depuis `stale.after_days` jours (7), ni brouillon, ni en pause, ni terminé ; dans le maître, une tâche en cours sans activité depuis autant. Une fois par période tant que rien ne bouge. |
| `tma:digest` | jours ouvrés à 8 h (`notifications.digest`) | Envoie à chacun ce qui l'attend (réponses et validations, tickets à prendre, en cours, reprises du jour) et ce qui a été mis de côté pour lui depuis le dernier envoi. Les retards sont signalés et personne ne reçoit de courriel vide. |
| `model:prune` (`Models\Notice`) | chaque nuit | Efface les notifications envoyées depuis plus de `notifications.keep_days` jours (90). |

**Les destinataires** (`Workflow\Recipients`, table `tma_recipients`) : chaque ticket et chaque tâche a des
principaux, prévenus tout de suite de ce qui s'y passe (courriel et/ou cloche de Filament), et des secondaires,
prévenus dans le bilan. Ce qui est actif pour qui n'a rien choisi se règle dans `notifications.defaults` (`mail`,
`database`, `digest` : tous par défaut) ; chacun le change dans « Mes notifications » (sauf
`notifications.user_choice` à false). Sans bilan, ce qui devait y aller est seulement noté. L'auteur est principal
d'office, comme le contact au Support choisi à la transmission ; publier une tâche exige un principal chez le client. Le client nomme ses
correspondants du Support (le droit `tma.ticket.correspond`) et le Support par son compte passerelle, que le maître
traduit en
`recipients.support_defaults` ; le maître nomme ses membres et les correspondants du client (`GET users`). La cloche
demande `->databaseNotifications()` sur le panel.

**À la demande** : « Envoyer maintenant » (liste des tickets, droit `notify`) ou `tma:notify --grouped` envoie tout de
suite leur bilan à qui a des notifications en attente ; « Relancer » (fiche, droit `remind`) écrit un message
marqué relance et prévient tout de suite qui a la main.

**Chaque application prévient ses propres utilisateurs** : ce qui est fait ici par les événements du workflow, ce qui
arrive de l'autre application par l'état recopié (`Events\SnapshotMirrored`). Une même chose n'est dite qu'une fois à
une personne, et jamais à son auteur. Les exemples de `tma:demo` ne préviennent personne.

## Audit et nettoyage

**Le journal d'audit** (CDC §23, écran « Journal d'audit », lecture seule, droit `tma.audit.viewany`) retient, pour
chaque action sensible, l'auteur, l'application, la date, l'objet, les valeurs avant et après, et l'origine
(interface, API, commande, planificateur). Les actions retenues sont :

- le temps : toute saisie, modification ou suppression ;
- les champs des tickets et des tâches ;
- les applications clientes (sans leur jeton, jamais écrit) ;
- les forfaits et leurs seuils franchis ;
- les synchronisations reçues ou en échec ;
- les nettoyages.

Les changements d'état ont leur propre historique (`tma_state_transitions`). Les commandes et l'API agent attribuent
leurs écritures à leur utilisateur (`Support\Auditor::as()`).

**Le nettoyage** (`tma:cleanup`, CDC §18) supprime définitivement, après `cleanup.retention_days` (90) :

- les tickets et tâches supprimés, avec leur chronologie, leur historique, leur temps et leurs fichiers ;
- les entrées supprimées ;
- les fichiers dont l'objet n'existe plus ;
- les fichiers envoyés mais jamais rattachés.

Il ne touche jamais à ce qui a une saisie facturée. `--dry-run` compte sans rien supprimer. Le rapport est journalisé
et audité, et l'événement `CleanupFinished` est lancé. Il ne tourne seul que si `cleanup.schedule` est activé
(chaque dimanche à 3 h).

## Les droits

Les droits suivent le format de `filament-permission-manager`, sans en dépendre (`permissions:sync` les crée) :

- **tickets** :
  - `tma.ticket.viewany`, `view`, `create`, `edit`, `delete` ;
  - qui est qui : `correspond` (correspondant du Support, client), `handle` (membre du Support, maître) ;
  - à côté du workflow : `note` (écrire des notes et les archiver), `remind`, `notify`, `time`, `sync` ;
  - les forçages : `force-close`, `force-return` ;
  - avec filament-prism : `ai.orthography` (famille « Intelligence artificielle »).
- **tâches** : `tma.task.*`, avec `comment`, `note`, `time`, `sync`, la famille « Passages de la tâche »
  (`tma.task.workflow.{mouvement}`) et, avec filament-prism, `ai.orthography`.
- **applications clientes** : `tma.instance.*`.
- **forfaits** : `tma.allowance.*`, plus `reset` ;
- **journal d'audit** : `tma.audit.viewany`.
- **widget du forfait côté client** : `widgets.allowanceoverview.viewany`.

Les passages normaux d'un ticket ne sont pas des droits : ils suivent le tour, quels que soient les droits. Sans le
gestionnaire, les actions restent ouvertes à qui voit l'objet, comme dans les autres plugins.

## Les commandes `tma:*` (pour une IA)

Elles ne posent aucune question quand les options sont données. `--json` donne une sortie lisible par programme.

```bash
php artisan tma:list [--type=tickets|tasks|all] [--state=in_progress,awaiting_requester] [--app=Desapp] [--project=] [--search=] [--mine] [--all] [--json]
php artisan tma:show DES-0042|T-0007 [--json]
php artisan tma:log DES-0042 --hours=2 [--date=2026-10-07] [--non-billable] [--json]   (sur un ticket : pas de texte)
php artisan tma:log T-0007 --days=0.5 --summary-file=resume.md   (tâche : avec le message qui dit ce qui a été fait)
php artisan tma:log DES-0042 --minutes=30 --entry=01k…   (sur un message ou une note existants, voir tma:show)
php artisan tma:comment DES-0042 --body="…" [--note] [--json]
```

- **Jeton obligatoire** : `--token`, ou `TMA_CLI_TOKEN` dans `.env` (jamais versionné). C'est le jeton personnel
  Passport d'un utilisateur, et la commande a exactement ses droits.
- **Dans le maître**, la commande agit directement.
- **Dans une application cliente**, elle agit dans le maître par son API « agent », puisque le temps ne se saisit
  que là. Le jeton est alors celui d'un utilisateur du maître. `--local` la fait agir sur les copies de l'application
  (lecture, brouillons), avec un jeton de l'application.
- `tma:log` enregistre la durée (une saisie de temps) sur un message ou une note existants (`--entry`), ou sur la fiche
  entière. Sur une tâche projet, `--summary` écrit aussi le message qui la résume (`--internal` : une note) ; sur un
  ticket, le temps n'a pas de texte (un message y va toujours avec un passage).

## Tests

Les tests vivent dans l'application hôte (Filbreeze : `tests/Feature/Tma`).
