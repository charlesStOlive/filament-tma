# filament-tma

Suivi de TMA et de tâches projet pour Filament 5, entre une application **maître** (le centre de service,
Filbreeze) et des applications **clientes** (Desapp…).

- Cahier des charges : [docs/CDC-v0.1.md](docs/CDC-v0.1.md)
- Décisions de conception, qui prévalent sur le CDC : [docs/ADDENDUM.md](docs/ADDENDUM.md)

État : **lot 1a** — tickets, workflow, chronologie, en traitement local. La transmission au centre de service et
l'API arrivent au lot 1b (voir l'addendum, §11).

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

Dépendances : `charlesstolive/filament-state-fusion-enhanced` (les états), `filament/spatie-laravel-media-library-plugin`
(les fichiers). `charlesstolive/filament-permission-manager` est conseillé (les droits, voir plus bas).

### Variables d'environnement

| Variable | Rôle |
|---|---|
| `TMA_MODE` | `master` (centre de service) ou `client` (défaut) |
| `TMA_INSTANCE_UID` | identifiant global de l'installation, un ULID ; **ne change plus** une fois des tickets créés |
| `TMA_INSTANCE_NAME` | son nom affiché (défaut : `APP_NAME`) |
| `TMA_TICKET_PREFIX` | début des numéros des tickets créés ici : `DES` donne `DES-0042` |
| `TMA_MASTER_URL`, `TMA_MASTER_TOKEN` | mode client : le maître (lot 1b). Sans URL, seul le traitement local existe |
| `TMA_ATTACHMENTS_DISK` | disque **privé** des fichiers (défaut `local`) |

Un ULID : `php -r "require 'vendor/autoload.php'; echo Symfony\Component\Uid\Ulid::generate();"`.

## Le workflow

Huit états (`States\Ticket`), et des **mouvements** (`Workflow\Moves`) qui les relient. Chaque mouvement dit quelle
partie peut le faire : le **demandeur** (l'application source) ou l'**intervenant** (un collègue de l'application
source en traitement local, le maître en centre de service). Quand la main est à l'autre partie, on ne peut plus
qu'ajouter des commentaires, notes et fichiers.

| Mouvement | De | Vers | Partie |
|---|---|---|---|
| Soumettre | Brouillon | Soumis | demandeur |
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

Tout passe par `Workflow\TicketWorkflow::run()` (interface, API, commande, planificateur), qui contrôle l'état,
la partie et le droit, valide les données, écrit l'historique (`tma_state_transitions`) et lance
`Events\TicketTransitioned`. Une application peut ajouter un mouvement :
`app(TicketWorkflow::class)->register(new MonMouvement)`.

## La chronologie

`Workflow\Timeline` : commentaires (partagés), notes (restent dans l'application où elles sont écrites, jusqu'au
partage), réponses sur un niveau, résumés d'intervention, avec des fichiers. Ajouter une entrée ne change jamais
l'état du ticket.

Le HTML est nettoyé côté serveur à l'enregistrement (`Support\Html`) : ni script, ni attribut d'événement, ni iframe,
ni SVG, ni `style` ; liens http(s) et mailto. Les fichiers sont sur un disque privé, avec une liste blanche de types
contrôlée sur leur contenu, et ne se téléchargent que par `tma/media/{media}`, qui vérifie les droits à chaque fois.

## Les droits

Au format de `filament-permission-manager`, sans en dépendre (`permissions:sync` les crée) :

- `tma.ticket.viewany`, `view`, `create`, `edit`, `delete` ;
- `tma.ticket.comment`, `tma.ticket.note` (lire et écrire les notes internes), `tma.ticket.share-note` ;
- la famille « Passages du ticket » : `tma.ticket.workflow.{mouvement}`, joker `tma.ticket.workflow.*`.

Sans le gestionnaire, ces actions restent ouvertes à qui voit le ticket, comme dans les autres plugins. Qui a la main
compte toujours, quels que soient les droits : un ticket ne se modifie qu'en brouillon, ou par l'intervenant quand
la main est à lui.

## Tests

Les tests vivent dans l'application hôte (Filbreeze : `tests/Feature/Tma`).
