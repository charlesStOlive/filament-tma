# API filament-tma — v1

L'API qui relie le **maître** (Filbreeze) et les **applications clientes** (Desapp…). Les deux sens existent :

- un client appelle le maître pour transmettre un ticket, faire un mouvement du demandeur, envoyer un commentaire ;
- le maître appelle un client pour lui envoyer l'état d'un ticket du centre de service, ses fichiers, et lire ses
  utilisateurs.

Un client n'appelle jamais un autre client.

Toutes les routes sont sous `/api/tma/v1`, en JSON (`Accept: application/json`), en HTTPS en production. Les
dates sont en UTC (ISO 8601). Les identifiants sont les **ulid** globaux, identiques dans les deux applications.

## Mise en place d'un couple maître / client

Exemple avec Filbreeze (maître) et Desapp (client). Les deux utilisent Passport (guard `api`), que
`filament-permission-manager` installe. Le détail, pour les utilisateurs, est dans la page d'aide « Configuration de
départ » (`docs/knowledge-base/tma/demarrer.md`).

1. **Filbreeze** : `php artisan tma:install --mode=master --name=Filbreeze --prefix=FIL` (mode, identifiant généré,
   nom, préfixe, dans `.env`).
2. **Filbreeze** : **TMA › Applications clientes › Nouveau** (l'identifiant est proposé). À l'enregistrement, le maître
   crée le compte de service de Desapp (`Instance::ensureServiceAccount()`, sans rôle). Puis **Configuration de
   l'application** : un jeton Passport pour ce compte (`Support\ServiceAccount`, l'ancien est révoqué) et la commande à
   lancer dans Desapp. `TMA_PUBLIC_URL` (maître) propose l'adresse du maître vue des clients.
3. **Desapp** : lancer cette commande,
   `php artisan tma:install --mode=client --uid=… --master-url=… --master-token=… --master-account=centre-de-service@tma.local`.
   Elle écrit `.env` (`TMA_MODE`, `TMA_INSTANCE_UID`, `TMA_MASTER_URL`, `TMA_MASTER_TOKEN`, `TMA_MASTER_API_USER`),
   crée le compte par lequel le maître appellera Desapp, et **transmet son jeton** (`POST master/register`) ; le maître
   rappelle aussitôt Desapp (`GET ping`) et la commande dit si la liaison marche dans les deux sens.
4. **Filbreeze** : rien à coller, sauf si la transmission a échoué (la commande affiche alors le jeton : fiche ›
   **Saisir le jeton à la main**). **Tester** vérifie la connexion à tout moment.
5. **Filbreeze** : un worker de file doit tourner (`php artisan queue:work`) : les envois vers les clients passent
   par la file.
6. **Desapp** : donner `tma.ticket.correspond` aux rôles des correspondants du Support (eux seuls font des demandes et
   apparaissent chez le maître) ; **Filbreeze** : `tma.ticket.handle` aux membres du Support.

En développement, les deux Sail doivent se joindre : un réseau Docker commun (comme `shared-db`), et les URL des
conteneurs dans l'adresse du centre de service et dans celle de l'application cliente.

## Authentification et isolation

- Chaque appel porte `Authorization: Bearer <jeton Passport>`.
- **Côté maître**, le jeton désigne l'utilisateur de service d'**une** application cliente (`tma_instances.api_user_id`) :
  c'est de lui que le maître déduit l'application appelante, **jamais du contenu de la requête**. Une application
  ne voit et ne touche que ses propres tickets (`source_instance_uid`), et n'y agit qu'en **demandeur**. Une
  application désactivée est refusée (403).
- **Côté client**, seul l'utilisateur `TMA_MASTER_API_USER` est accepté, et le maître n'agit qu'en **intervenant**,
  sur les tickets de cette application.
- Les routes de l'autre mode répondent 404.
- Limite : 120 requêtes par minute.

## Fiabilité

- **Idempotence.** Un ticket, une entrée, une transition, un fichier sont identifiés par leur ulid (ou `uuid` pour un
  fichier) : rejouer une requête ne crée rien de plus. Un mouvement porte un `transition_ulid` choisi par
  l'appelant : rejoué, il n'est pas refait.
- **Client → maître : synchrone.** Si le maître ne répond pas, ou refuse, l'opération est annulée chez le client et
  l'utilisateur voit l'erreur.
- **Maître → client : par la file**, avec des tentatives espacées (`filament-tma.api.sync`). Après la dernière, le
  ticket passe « synchronisation en erreur » (`last_sync_error`), l'événement `TicketSyncFailed` est lancé, et
  « Renvoyer à l'application » le relance.
- **Versions.** Le maître incrémente `version` à chaque envoi ; un client ignore les champs d'une version plus
  ancienne que celle qu'il a.

## Erreurs

| Code | Sens |
|---|---|
| 401 | jeton absent ou invalide |
| 403 | jeton d'un autre compte, application désactivée, ou mouvement d'une autre partie |
| 404 | ticket inconnu de cette application, ou route de l'autre mode |
| 422 | données invalides (`errors` par champ), message modifié après qu'un autre a écrit, ticket d'une autre application |
| 429 | trop de requêtes |

## Objets

### Acteur

Une personne, dans l'application où elle existe.

```json
{ "instance_uid": "01K…", "id": "7", "name": "Ada Lovelace", "email": "ada@exemple.fr" }
```

### Ticket

```json
{
  "ulid": "01k…", "number": "DES-0042",
  "source_instance_uid": "01K…", "created_on_instance_uid": "01K…",
  "title": "Export des parrains", "description": "<p>…</p>",
  "priority": "low|normal|high|urgent",
  "opening": "request|on_behalf|outreach|notice",
  "state": "draft|submitted|in_progress|awaiting_requester|paused|pending_validation|closed|cancelled",
  "task_ulid": null,
  "requester": { "…acteur" }, "assignee": { "…acteur" } , "created_by": { "…acteur" },
  "submitted_at": "…", "taken_at": "…", "waiting_since": "…", "paused_until": "…",
  "validation_requested_at": "…", "closed_at": "…", "cancelled_at": "…",
  "last_activity_at": "…", "auto_close_warned_at": "…", "created_at": "…",
  "version": 3,
  "time": { "total_minutes": 240, "by_month": { "2026-01": 120, "2026-02": 120 } },
  "files_count": 1, "file_uuids": [ "…" ]
}
```

`files_count`, `file_uuids` : les pièces jointes du ticket, sans leur contenu. Dans un état complet, celle qui reçoit
retire celles qui n'y sont plus ; s'il lui en manque, elle réclame le ticket par son ulid dans `missing_media`, et les
reçoit par `POST media/ticket/{ulid}`. Absents (application d'avant), rien ne change.

`opening` : comment le ticket s'est ouvert — une demande du client (`request`), le Support au nom d'un client
(`on_behalf`), le Support qui s'adresse au client et attend sa réponse (`outreach`), ou qui l'informe (`notice`, clos dès
sa publication). Absent (application d'avant), ce qu'on sait reste.

`task_ulid` : la tâche projet dont le ticket est parti (« Créer un ticket » sur sa fiche), ou null. Celle qui reçoit
le relie à sa copie de la tâche si elle la connaît ; absent, le lien qu'elle sait reste.

Le HTML (`description`, `body`) est nettoyé à la réception, quel qu'il soit.

### Entrée de chronologie

```json
{
  "ulid": "01k…", "ticket_ulid": "01k…", "parent_ulid": null,
  "type": "comment|note", "number": null, "private": false, "body": "<p>…</p>",
  "author": { "…acteur" }, "origin_instance_uid": "01K…", "origin": "ui|api|command|scheduler",
  "shared_at": null, "meta": { "question": true }, "created_at": "…",
  "edited_at": null, "archived_at": null, "deleted_at": null,
  "files_count": 1, "file_uuids": [ "…" ],
  "files": [ { "uuid": "…", "name": "capture", "file_name": "capture.png", "mime_type": "image/png", "size": 2048, "content": "<base64>" } ]
}
```

- `comment` est un **message** ; `note`, une **note**. Les deux sont lus de tous, des deux côtés, sauf une note
  `private` : elle voyage comme les autres, mais ne se montre qu'à son auteur.
- `parent_ulid` : pour un message, la question à laquelle il répond (un seul niveau) ; pour une note, le message de
  premier niveau qu'elle commente (sans parent, elle porte sur le ticket entier).
- `number` : le numéro d'une note dans son ticket. Celle qui reçoit le garde s'il est libre chez elle, sinon elle
  prend le suivant et le dit dans sa réponse.
- `meta` : `transition` (le passage écrit avec ce message), `reminder` (un message de relance : qui a la main chez
  celle qui reçoit est prévenu tout de suite).
- `edited_at` (message ou note modifié), `archived_at` (note archivée), `deleted_at` (note supprimée) : absents,
  ce que sait l'application qui reçoit ne change pas.
- `file_uuids` : les fichiers de l'entrée ; ceux qui n'y sont plus sont retirés chez celle qui reçoit.
- `shared_at` ne sert plus qu'aux applications d'avant les notes lues de tous, qui n'acceptaient une note que
  « partagée » : il vaut la date de la note, et null pour une note privée (qu'elles ignorent donc).
- `files` : contenu en base64. Les types suivent la liste blanche de l'application qui reçoit
  (`filament-tma.attachments.accepted_types`), sur le contenu réel ; taille maximale `attachments.max_size_kb`.

### Transition

```json
{
  "ulid": "01k…", "move": "request-answer", "from_state": "submitted", "to_state": "awaiting_requester",
  "actor": { "…acteur" }, "origin_instance_uid": "01K…", "origin": "ui", "reason": null, "created_at": "…"
}
```

### Tâche projet

```json
{
  "ulid": "01k…", "number": "T-0007", "target_instance_uid": "01K…",
  "project": {
    "ulid": "01k…", "title": "Portail parrain", "description": null,
    "estimate_min_days": 1.0, "estimate_max_days": 3.0,
    "members": [ { "…acteur" } ]
  },
  "title": "Refonte de la page parrain", "description": "<p>…</p>",
  "state": "in_progress|done|cancelled",
  "starts_on": "2026-10-01", "due_on": "2026-11-15",
  "assignee": { "…acteur" }, "created_by": { "…acteur" },
  "published_at": "…", "done_at": null, "cancelled_at": null, "last_activity_at": "…", "created_at": "…",
  "deleted_at": null,
  "version": 2,
  "time": { "total_minutes": 630, "by_month": { "2026-09": 420, "2026-10": 210 } },
  "files_count": 2, "file_uuids": [ "…" ]
}
```

Une tâche en brouillon (`draft`) n'est jamais envoyée. `project` : son projet (null pour une tâche d'avant les
projets, voir « Projet ») ; celle qui reçoit le crée ou le met à jour, par son `ulid`. Le temps d'un projet est la
somme de celui de ses tâches : il ne voyage pas à part. `files_count`, `file_uuids` : ses pièces
jointes, comme pour un ticket (`POST media/task/{ulid}`). `deleted_at` : la tâche a été supprimée dans le maître (avec ses
entrées, envoyées supprimées elles aussi) ; celle qui reçoit la retire, et n'en crée pas une qu'elle ne connaissait pas.

### Projet

```json
{
  "ulid": "01k…", "target_instance_uid": "01K…", "title": "Portail parrain", "description": null,
  "estimate_min_days": 1.0, "estimate_max_days": 3.0,
  "members": [ { "…acteur" } ],
  "deleted_at": null
}
```

Il part seul dès qu'il est créé, modifié (titre, description, fourchette, participants) ou supprimé, même sans tâche
publiée (`POST sync`, `kind` = `project`), et avec chacune de ses tâches. La fourchette est en jours (null sans
borne) ; `members` : ses participants nommés, des acteurs ; absents (maître d'avant), ce que le client sait reste.
`deleted_at` : supprimé dans le maître, le client le retire. Un projet qui change d'application (possible tant qu'il
n'a pas de tâche) part supprimé chez l'ancienne.

### État complet (snapshot)

```json
{ "kind": "ticket", "ticket": { "…" }, "entries": [ "…toutes les entrées, notes supprimées comprises, sans fichiers" ], "transitions": [ "…" ], "recipients": [ "…" ] }
{ "kind": "task", "task": { "…" }, "entries": [ "…" ], "transitions": [ "…" ], "recipients": [ "…" ] }
```

### Destinataires

```json
[ { "actor": { "…acteur" }, "role": "primary|secondary", "support": false } ]
```

Les personnes à prévenir : un principal tout de suite, un secondaire dans son bilan (`Workflow\Recipients`). Chaque
application prévient les siennes. `support: true` : le compte passerelle d'une application cliente, qui tient lieu du
Support ; le maître le remplace par ses destinataires par défaut (`recipients.support_defaults`) et ne le garde pas.
Dans un état complet, la liste du maître remplace celle du client ; un client d'avant les destinataires l'ignore.

### État d'un forfait

```json
{
  "allowance_ulid": "01k…", "label": "Desapp — Contrat 2026", "contract": "Contrat 2026",
  "period": "monthly|quarterly", "period_key": "2026-10", "from": "2026-10-01", "to": "2026-10-31",
  "allocated_minutes": 840, "consumed_minutes": 600, "remaining_minutes": 240, "overage_minutes": 0,
  "percent": 71.4, "by_month": { "2026-10": 600 }, "instances": [ "01K…" ]
}
```

## Routes du maître (appelées par un client)

### `GET master/ping`

```json
{ "api": "v1", "mode": "master", "instance_uid": "01K…", "name": "Filbreeze" }
```

### `POST master/register` — le jeton par lequel le maître appellera l'application

```json
{ "token": "eyJ…" }
```

Le maître le range (chiffré) dans la fiche de l'application appelante, puis l'appelle (`GET ping`) avec. Réponse :

```json
{ "instance": "Desapp", "callback": true, "callback_error": null }
```

`callback: false` et la raison si le rappel échoue (adresse de l'application à corriger dans le maître). Utilisé par
`tma:install --master-account=…`.

### `POST tickets` — transmettre au centre de service

```json
{
  "ticket": { "…ticket, tel qu'il est chez le client" },
  "entries": [ "…messages et notes, avec leurs fichiers" ],
  "files": [ "…pièces jointes du ticket" ],
  "transition": { "…la transition transmit, avec son ulid" },
  "recipients": [ "…destinataires vus du client, le Support compris" ]
}
```

Le maître crée le ticket en **Soumis**, au centre de service, rattaché au client de l'instance, avec ses
destinataires (toujours au moins un principal chez lui). Réponse : `201` (ou `200` si déjà transmis) avec l'état
complet.

### `POST tickets/{ulid}/recipients` et `POST tasks/{ulid}/recipients`

```json
{ "recipients": [ "…destinataires vus du client" ], "actor": { "…qui les change" } }
```

Les personnes de l'application appelante remplacent celles qu'avait le maître ; le Support (`support: true`) devient
ses destinataires par défaut ; un membre du Support nommé est ajouté, jamais retiré. Les personnes ajoutées sont
prévenues. Réponse : l'état complet, à recopier.

### `GET tickets/{ulid}`

L'état complet d'un ticket de l'application appelante.

### `PATCH tickets/{ulid}` — la demande modifiée par son auteur

`{ ticket: { title, description, priority }, files, file_uuids, actor }`. L'auteur de la demande (`actor`, qui doit
être celui qui l'a créée) la modifie tant qu'aucun message ne l'a suivie (addendum §3.2) ; sinon 403. `files` : les
pièces jointes de la demande (`Payload::files()`), `file_uuids` : celles qui restent (les autres sont retirées).
Réponse : l'état complet (`Payload::snapshot()`).

### `POST tickets/{ulid}/moves` — un mouvement du demandeur

```json
{ "move": "answer", "data": { "answer": "<p>…</p>" }, "actor": { "…acteur" }, "transition_ulid": "01k…" }
```

Mouvements du demandeur : `answer`, `validate`, `reopen`, `cancel` (tant que le ticket est Nouveau), `take-over`. Les données suivent
les règles du mouvement (voir `Workflow\Moves`) et le tour : seul le demandeur (`actor`) fait un passage du
demandeur ; « Prendre la main » (`take-over`), un autre destinataire principal ; un mouvement du Support répond 403. Réponse : l'état
complet, à recopier.

### `POST tickets/{ulid}/entries` — un message, une réponse, une note ; ou leur changement

Corps : une entrée (avec `files`). Réponse : `201` et l'entrée enregistrée (`entry`, avec son `number`), ou `200`
si elle était déjà reçue. Une nouvelle entrée de l'application cliente est une note, ou un message marqué relance
(`meta.reminder`) : le demandeur parle en répondant, par un passage (403 sinon). Sur un ticket terminé, seule une note
est acceptée (422 sinon).

Renvoyée avec le même `ulid`, une entrée **change** :

- une entrée de l'application appelante prend le nouveau texte, `edited_at`, `archived_at`, `deleted_at` et ses
  fichiers. Un message qui n'est plus le dernier message du ticket ne change plus (422) ;
- sur une entrée du maître, seul `archived_at` d'une note est pris (archiver ou ressortir une note du Support).

L'auteur et l'origine d'une entrée ne changent jamais.

### `GET tasks/{ulid}` et `POST tasks/{ulid}/entries`

L'état d'une tâche projet publiée pour l'application appelante, et ce qu'elle y ajoute ou change (message, réponse,
note, avec ses fichiers), comme pour un ticket. Une tâche en brouillon répond 404. Sur une tâche terminée, seules
les notes sont acceptées.

## Routes « agent » du maître (appelées par ses utilisateurs)

Pour les commandes `tma:*` lancées dans une application cliente. Le jeton est le jeton **personnel** d'un
utilisateur du maître, dont l'appel a exactement les droits ; le compte de service d'une application cliente y est
refusé (403). `{ref}` : le numéro (`DES-0042`, `T-0007`) ou l'ulid du ticket ou de la tâche.

| Route | Rôle |
|---|---|
| `GET agent/tickets?type=all|tickets|tasks&state=&app=&project=&search=&mine=1&all=1&limit=` | la liste des tickets et des tâches (ouverts par défaut) |
| `GET agent/tickets/{ref}` | un ticket, sa description et sa chronologie en texte |
| `POST agent/tickets/{ref}/time` `{ minutes, worked_on?, summary?, entry_ulid?, billable?, internal? }` | une saisie de temps : sur le message que `summary` écrit (une note avec `internal`), sur un message ou une note existants (`entry_ulid`, pas avec `summary`), ou sur la fiche entière ; réponse : la saisie, `entry`, `snapshot` |
| `POST agent/tickets/{ref}/entries` `{ body, note? }` | un message, ou une note sur le ticket entier |
| `POST agent/tickets/{ref}/entries` `{ type, body, parent_ulid?, files?, private?, reminder? }` | un message ou une note d'agent (`reminder` : un message de relance, `meta.reminder`) ; la réponse donne aussi l'état à recopier (`snapshot`) |
| `PATCH agent/tickets/{ref}/entries/{ulid}` `{ body?, files?, remove_files?, private?, archived?, delete? }` | l'agent modifie son message ou sa note (la garde pour lui ou la montre : `private`), archive une note (`archived`), supprime la sienne (`delete`) ; réponse : `snapshot` |
| `POST agent/tickets/{ref}/moves` `{ move, data?, transition_ulid? }` | un passage, avec les droits de l'agent ; réponse : `snapshot` |

**Jetons d'agent.** Un jeton nommé `tma-agent:{identifiant d'une application}` (émis par « Agents du centre de
service ») ne vaut que pour les tickets et tâches de cette application, avec les droits de la personne dans le maître.
L'application cliente s'en sert quand un de ses utilisateurs, agent du centre de service, agit sur un ticket du centre
de service (voir l'aide « Les agents du centre de service »).

Réponses : `{ "data": … }` ; 404 pour un ticket introuvable.

## Routes du client (appelées par le maître)

### `GET ping`

```json
{ "api": "v1", "mode": "client", "instance_uid": "01K…", "name": "Desapp" }
```

### `GET users`

Les correspondants du Support de l'application (`tma.ticket.correspond`) : demandeurs possibles d'un ticket préparé
par le maître, et destinataires possibles. `service_desk` est toujours vrai (gardé pour les versions d'avant). Jamais
les membres du Support ni le compte du maître.

```json
{ "data": [ { "id": "7", "name": "Ada Lovelace", "email": "ada@exemple.fr", "service_desk": true } ] }
```

Le maître garde la liste en cache (`api.users_cache_ttl`, une heure), avec un bouton « Rafraîchir ».

### `POST sync` — l'état d'un ticket du centre de service, d'une tâche projet ou d'un projet

Corps : un état complet (`kind` : `ticket` ou `task`), ou un projet seul : `{ "kind": "project", "project": { "…" } }`.

- **Ticket** : il doit être de cette application (`source_instance_uid`), au centre de service, et déjà connu,
  sauf s'il a été préparé par le maître pour elle et publié (`created_on_instance_uid` est celui du maître ; jamais
  un brouillon) : 404 sinon.
- **Tâche** : destinée à cette application (`target_instance_uid`), jamais en brouillon (422) ; elle est créée à sa
  première réception (`Events\TaskReceived`).
- **Projet** : destiné à cette application (`target_instance_uid`, 422 sinon) ; créé à sa première réception, même
  sans tâche ; un projet inconnu qui arrive supprimé n'est pas créé. La réponse est toujours `{ "missing_media": [] }`.

Le client recopie, puis prévient lui-même ses utilisateurs de ce qui est nouveau : passages, messages, notes,
relances, tâche publiée (`Events\SnapshotMirrored`, `Support\Notifier`), avertissement avant clôture. Réponse :

```json
{ "missing_media": [ "01k…" ] }
```

les entrées dont le client attend encore les fichiers, et l'ulid du ticket ou de la tâche s'il lui manque de leurs
pièces jointes : un fichier manque quand son identifiant est dans `file_uuids` et pas chez lui (une image remplacée par
une autre manque donc, même si leur nombre ne change pas).

### `POST media/{entry|task|ticket}/{ulid}`

```json
{ "files": [ "…fichiers en base64" ] }
```

Réponse : `{ "files_count": 2 }`.

### `POST agents` et `POST agents/unlink` — les agents du centre de service

```json
{ "email": "charles@notilac.fr", "name": "Charles", "master_uid": "01K…", "master_user_id": "1", "token": "eyJ…" }
```

Relie l'utilisateur d'ici qui a cet e-mail (404 s'il n'existe pas) : sur un ticket ou une tâche du centre de service,
il agit pour le centre de service, avec ce jeton. `agents/unlink` `{ email }` retire le lien. Les agents ne figurent
pas dans `GET users`.

### `POST allowances` — l'état des forfaits

```json
{ "data": [ "…états de forfait (période en cours)" ] }
```

Remplace l'état précédent ; un forfait qui ne couvre pas cette application est ignoré. Réponse :
`{ "count": 1 }`. Envoyé par la file après chaque saisie de temps qui touche un forfait, et quand un forfait est
créé ou modifié.
