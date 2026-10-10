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
6. **Desapp** : donner `tma.ticket.workflow.transmit` aux rôles qui peuvent transmettre au centre de service. Les
   autres utilisateurs n'ont que le traitement local et n'apparaissent pas chez le maître.

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
| 422 | données invalides (`errors` par champ), note interne envoyée, ticket d'une autre application |
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
  "state": "draft|submitted|in_progress|awaiting_requester|paused|pending_validation|closed|cancelled",
  "handling_mode": "local|service_desk",
  "due_on": "2026-10-31",
  "requester": { "…acteur" }, "assignee": { "…acteur" } , "created_by": { "…acteur" },
  "submitted_at": "…", "taken_at": "…", "waiting_since": "…", "paused_until": "…",
  "validation_requested_at": "…", "closed_at": "…", "cancelled_at": "…",
  "last_activity_at": "…", "auto_close_warned_at": "…", "created_at": "…",
  "version": 3,
  "time": { "total_minutes": 240, "by_month": { "2026-01": 120, "2026-02": 120 } }
}
```

Le HTML (`description`, `body`) est nettoyé à la réception, quel qu'il soit.

### Entrée de chronologie

```json
{
  "ulid": "01k…", "ticket_ulid": "01k…", "parent_ulid": null,
  "type": "comment|note", "body": "<p>…</p>",
  "author": { "…acteur" }, "origin_instance_uid": "01K…", "origin": "ui|api|command|scheduler",
  "shared_at": null, "meta": { "question": true }, "created_at": "…",
  "files_count": 1,
  "files": [ { "uuid": "…", "name": "capture", "file_name": "capture.png", "mime_type": "image/png", "size": 2048, "content": "<base64>" } ]
}
```

- `comment` est un **message** (lu de tous, demandeur compris) ; `note`, une **note interne**.
- Une **note** n'est transmise que partagée (`shared_at` non nul) ; une note non partagée est refusée (422).
- `parent_ulid` : une réponse, sur un seul niveau.
- `files` : contenu en base64. Les types suivent la liste blanche de l'application qui reçoit
  (`filament-tma.attachments.accepted_types`), sur le contenu réel ; taille maximale `attachments.max_size_kb`.

### Transition

```json
{
  "ulid": "01k…", "move": "take-charge", "from_state": "submitted", "to_state": "in_progress",
  "actor": { "…acteur" }, "origin_instance_uid": "01K…", "origin": "ui", "reason": null, "created_at": "…"
}
```

### Tâche projet

```json
{
  "ulid": "01k…", "number": "T-0007", "target_instance_uid": "01K…", "project": "Portail parrain",
  "title": "Refonte de la page parrain", "description": "<p>…</p>",
  "state": "in_progress|done|cancelled",
  "starts_on": "2026-10-01", "ends_on": "2026-11-15",
  "assignee": { "…acteur" }, "created_by": { "…acteur" },
  "published_at": "…", "done_at": null, "cancelled_at": null, "last_activity_at": "…", "created_at": "…",
  "version": 2,
  "time": { "total_minutes": 630, "by_month": { "2026-09": 420, "2026-10": 210 } }
}
```

Une tâche en brouillon (`draft`) n'est jamais envoyée.

### État complet (snapshot)

```json
{ "kind": "ticket", "ticket": { "…" }, "entries": [ "…entrées partagées, sans fichiers" ], "transitions": [ "…" ] }
{ "kind": "task", "task": { "…" }, "entries": [ "…" ], "transitions": [ "…" ] }
```

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
  "entries": [ "…commentaires et notes partagées, avec leurs fichiers" ],
  "files": [ "…pièces jointes du ticket" ],
  "transition": { "…la transition transmit, avec son ulid" }
}
```

Le maître crée le ticket en **Soumis**, au centre de service, rattaché au client de l'instance. Réponse : `201`
(ou `200` si déjà transmis) avec l'état complet.

### `GET tickets/{ulid}`

L'état complet d'un ticket de l'application appelante.

### `POST tickets/{ulid}/moves` — un mouvement du demandeur

```json
{ "move": "answer", "data": { "answer": "<p>…</p>" }, "actor": { "…acteur" }, "transition_ulid": "01k…" }
```

Mouvements du demandeur : `answer`, `validate`, `reopen`, `cancel` (avant prise en charge). Les données suivent
les règles du mouvement (voir `Workflow\Moves`). Un mouvement de l'intervenant répond 403. Réponse : l'état
complet, à recopier.

### `POST tickets/{ulid}/entries` — un commentaire, une réponse, une note partagée

Corps : une entrée (avec `files`). Réponse : `201`, ou `200` si l'entrée était déjà reçue. Sur un ticket
terminé, seules les notes sont acceptées (422 sinon).

### `GET tasks/{ulid}` et `POST tasks/{ulid}/entries`

L'état d'une tâche projet publiée pour l'application appelante, et ce qu'elle y ajoute (commentaire, réponse, note
partagée, avec ses fichiers). Une tâche en brouillon répond 404. Sur une tâche terminée, seules les notes sont
acceptées.

## Routes « agent » du maître (appelées par ses utilisateurs)

Pour les commandes `tma:*` lancées dans une application cliente. Le jeton est le jeton **personnel** d'un
utilisateur du maître, dont l'appel a exactement les droits ; le compte de service d'une application cliente y est
refusé (403). `{ref}` : le numéro (`DES-0042`, `T-0007`) ou l'ulid du ticket ou de la tâche.

| Route | Rôle |
|---|---|
| `GET agent/tickets?type=all|tickets|tasks&state=&app=&project=&search=&mine=1&all=1&limit=` | la liste des tickets et des tâches (ouverts par défaut) |
| `GET agent/tickets/{ref}` | un ticket, sa description et sa chronologie en texte |
| `POST agent/tickets/{ref}/time` `{ minutes, worked_on?, summary?, billable?, internal? }` | une saisie de temps et son résumé |
| `POST agent/tickets/{ref}/entries` `{ body, note? }` | un commentaire, ou une note interne |
| `POST agent/tickets/{ref}/entries` `{ type, body, parent_ulid?, files? }` | un message d'agent ; la réponse donne aussi l'état à recopier (`snapshot`) |
| `POST agent/tickets/{ref}/moves` `{ move, data?, transition_ulid? }` | un passage, avec les droits de l'agent ; réponse : `snapshot` |
| `POST agent/tickets/{ref}/time` `{ minutes, worked_on?, description?, billable? }` | une saisie de temps seule ; réponse : `snapshot` |

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

Les utilisateurs qui ont des droits sur le centre de service (`tma.ticket.workflow.transmit`).
`?include_local_only=1` ajoute ceux qui voient les tickets sans pouvoir les transmettre.

```json
{ "data": [ { "id": "7", "name": "Ada Lovelace", "email": "ada@exemple.fr", "service_desk": true } ] }
```

Le maître garde la liste en cache (`api.users_cache_ttl`, une heure), avec un bouton « Rafraîchir ».

### `POST sync` — l'état d'un ticket du centre de service, ou d'une tâche projet

Corps : un état complet (`kind` : `ticket` ou `task`).

- **Ticket** : il doit être de cette application (`source_instance_uid`), au centre de service, et déjà connu,
  sauf s'il a été préparé par le maître pour elle et publié (`created_on_instance_uid` est celui du maître ; jamais
  un brouillon) : 404 sinon.
- **Tâche** : destinée à cette application (`target_instance_uid`), jamais en brouillon (422) ; elle est créée à sa
  première réception (`Events\TaskReceived`).

Le client recopie, puis prévient lui-même ses utilisateurs (avertissement avant clôture, clôture automatique, et
les passages de `notifications.immediate`). Réponse :

```json
{ "missing_media": [ "01k…" ] }
```

les entrées dont le client attend encore les fichiers.

### `POST media/entry/{ulid}`

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
