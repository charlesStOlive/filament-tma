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
`filament-permission-manager` installe.

1. **Desapp** : `.env`

   ```dotenv
   TMA_MODE=client
   TMA_INSTANCE_UID=01K…        # un ULID, propre à Desapp, définitif
   TMA_TICKET_PREFIX=DES
   TMA_MASTER_URL=https://filbreeze.example
   TMA_MASTER_TOKEN=…           # étape 3
   TMA_MASTER_API_USER=filbreeze@api.local   # étape 2 (id ou e-mail)
   ```

2. **Desapp** : créer un utilisateur de service, par exemple `filbreeze@api.local`, sans rôle, et lui émettre un
   jeton (écran de profil de filament-permission-manager, ou `ApiTokenService::createToken()`). C'est par ce jeton
   que le maître appellera Desapp. Seul cet utilisateur est accepté par l'API du client.

3. **Filbreeze** : créer un utilisateur de service, par exemple `desapp@api.local`, et lui émettre un jeton. Ce
   jeton va dans `TMA_MASTER_TOKEN` de Desapp.

4. **Filbreeze** : écran **TMA › Applications clientes** › Nouveau :
   - identifiant = `TMA_INSTANCE_UID` de Desapp ; adresse = l'URL de Desapp ; client = la société ;
   - compte de service = `desapp@api.local` (étape 3) ; jeton = celui de l'étape 2.
   - Bouton **Tester** : le maître appelle `GET ping` de Desapp et vérifie l'identifiant.

5. **Filbreeze** : un worker de file doit tourner (`php artisan queue:work`) : les envois vers les clients passent
   par la file.

6. **Desapp** : donner `tma.ticket.workflow.transmit` aux rôles qui peuvent transmettre au centre de service. Les
   autres utilisateurs n'ont que le traitement local et n'apparaissent pas chez le maître.

En développement, les deux Sail doivent se joindre : un réseau Docker commun (comme `shared-db`), et les URL des
conteneurs (`http://filbreeze.test`, `http://desapp.test`…) dans `TMA_MASTER_URL` et l'adresse de l'instance.

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
  "type": "comment|note|summary", "body": "<p>…</p>",
  "author": { "…acteur" }, "origin_instance_uid": "01K…", "origin": "ui|api|command|scheduler",
  "shared_at": null, "meta": { "question": true }, "created_at": "…",
  "files_count": 1,
  "files": [ { "uuid": "…", "name": "capture", "file_name": "capture.png", "mime_type": "image/png", "size": 2048, "content": "<base64>" } ]
}
```

- Une **note** n'est transmise que partagée (`shared_at` non nul) ; une note interne est refusée (422).
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

### État complet (snapshot)

```json
{ "ticket": { "…" }, "entries": [ "…entrées partagées, sans fichiers" ], "transitions": [ "…" ] }
```

## Routes du maître (appelées par un client)

### `GET master/ping`

```json
{ "api": "v1", "mode": "master", "instance_uid": "01K…", "name": "Filbreeze" }
```

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

### `POST sync` — l'état d'un ticket du centre de service

Corps : un état complet. Le ticket doit être de cette application (`source_instance_uid`), au centre de
service, et déjà connu (404 sinon). Le client recopie, puis prévient lui-même son demandeur (avertissement
avant clôture, clôture automatique). Réponse :

```json
{ "missing_media": [ "01k…" ] }
```

les entrées dont le client attend encore les fichiers.

### `POST media/entry/{ulid}`

```json
{ "files": [ "…fichiers en base64" ] }
```

Réponse : `{ "files_count": 2 }`.
