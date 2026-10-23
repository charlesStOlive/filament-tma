# Addendum au CDC v0.1 — décisions de conception

Ce document complète [CDC-v0.1.md](CDC-v0.1.md) et **prévaut sur lui** en cas de divergence.
Il rassemble les décisions prises le 08/10/2026 à la relecture du CDC.

Vocabulaire : **maître** = Filbreeze, **client** = une application cliente (Desapp…).

---

## 1. Écarts entre le CDC et l'existant

| Point du CDC | Réalité | Décision |
|---|---|---|
| « Relier ultérieurement au CRM » (§2.1, §14.5) | Filbreeze **est** le CRM (cluster `Crm` : `Company`, `Contact`, `Quote`, `Invoice`, export Qonto) | En mode maître, « Client » = un modèle de l'hôte déclaré en config (`App\Models\Company` dans Filbreeze). Pas de passerelle CRM abstraite. |
| « Ligne de facture » (§6.9) | `Invoice.items` est un champ JSON (Builder Filament), sans table de lignes | Traité au lot 3 (voir §9). |
| « Prism pour l'affichage » (§20) | `charlesstolive/filament-prism` sert aux échanges avec des IA | Prism n'est pas une dépendance d'affichage. Usage IA éventuel plus tard (résumé, orthographe). |
| Rendu graphique | `charlesstolive/filament-ui` (couleurs, `SplitView`, `SidePane`…) | Un composant de rendu réutilisable par d'autres plugins va dans filament-ui. Un rendu propre au plugin reste dans son CSS/JS, avec les couleurs de filament-ui. |
| « Permissions Sync » (§13) | `charlesstolive/filament-permission-manager` (permissions, jetons API Passport, middlewares `fpm.api.*`) | Les droits et l'authentification API s'appuient dessus. |
| Workflow (§7) | `charlesstolive/filament-state-fusion-enhanced` (sur `spatie/laravel-model-states`) | Les états sont des états spatie, affichés avec state-fusion ; les passages sont les mouvements du plugin (§3). |
| Pièces jointes (§17) | `spatie/laravel-medialibrary` présent dans les deux applications | Pièces jointes et images via medialibrary. |

Package : `charlesstolive/filament-tma`, dans `packages_filament/filament-tma`.

---

## 2. Un seul circuit : le demandeur et le Support (16/10/2026)

Remplace le « demandeur / intervenant » et les deux modes de traitement des débuts.

Un ticket va **toujours** d'une application cliente au Support. Il a deux parties :

- le **demandeur** : **une personne** de l'application cliente, celle qui a fait la demande (ou qui l'a reprise,
  §3.3) ;
- le **Support** : les membres du Support, dans le maître.

Le maître fait autorité dès que le ticket quitte le brouillon : l'application cliente lui demande chaque passage, il
l'exécute, elle recopie le résultat (§7).

**Le traitement interne est retiré** (traitement par l'équipe du client sans le Support, « Confier à l'équipe EDM »,
escalade d'un ticket local, utilisateurs « internes », colonne `handling_mode`). S'il revient un jour, il aura son
propre workflow, à part de celui-ci.

Les droits ne disent plus quel passage chacun peut faire (§3) : ils disent qui est qui, et ouvrent quelques actions à
côté du workflow.

| Droit | Où | Ce qu'il donne |
|---|---|---|
| `tma.ticket.correspond` — **Correspondant du Support** | client | faire une demande et la transmettre, en être le demandeur, être nommé par le Support (destinataire, demandeur d'un ticket préparé : `GET users`) |
| `tma.ticket.handle` — **Membre du Support** | maître | traiter les demandes (§3.1), préparer un ticket pour un client |
| `tma.ticket.force-close`, `tma.ticket.force-return` | les deux | les forçages (§3.4) |
| `note`, `remind`, `notify`, `time`, `sync` | selon le cas | les actions à côté du workflow : notes, relance, notifications groupées, temps, synchro |
| `viewany`, `view`, `create`, `edit`, `delete` | les deux | la liste et la fiche, comme ailleurs |

Un membre du Support relié à une application cliente (`Models\Agent`) y agit pour le Support, avec ses droits du
maître ; il n'y est jamais correspondant.

---

## 3. Workflow des tickets (16/10/2026)

Remplace l'ancien §3. Les états sont des états `spatie/laravel-model-states` (affichés avec
`filament-state-fusion-enhanced`) ; les passages sont les **mouvements** du plugin (`Workflow\TicketWorkflow`), parce
que plusieurs passages relient les mêmes états. Le moteur contrôle tout, quelle que soit l'origine (interface, API,
commande) : masquer un bouton n'est jamais le seul contrôle.

### 3.1 Le tour

**Chaque état dit à qui est le tour. Dans le circuit normal, seule cette partie agit, et du côté du demandeur,
seulement le demandeur lui-même.** Les passages normaux ne sont pas des droits : aucun droit, pas même Super Admin, ne
permet d'agir hors de son tour. Côté Support, tout membre du Support agit, pas seulement le contact du ticket.

**Pas de « prise en charge »** (16/10/2026, remplace le passage « Prendre en charge ») : en transmettant, le demandeur
choisit son **contact au Support** parmi les membres du Support reliés à son application — d'office s'il n'y en a
qu'un ; sans membre relié, le maître le choisit (le seul membre par défaut, s'il n'y en a qu'un). Le ticket arrive
chez ce contact, en Nouveau, et le Support répond tout de suite. Un ticket préparé par le maître a son auteur pour
contact.

| État | Le tour de | Passages normaux |
|---|---|---|
| **Brouillon** | son auteur | « Transmettre au Support » → Nouveau · « Retirer » → Annulé (sans motif) · pour un ticket préparé par le maître : « Publier chez EDM » → Nouveau |
| **Nouveau** | Support | « Demander des précisions » (question) → En attente du demandeur · « Marquer comme résolu » → Résolu · « Mettre en pause » (date) → En pause · « Refuser » (motif) → Annulé |
| **En cours** | Support | les mêmes, sauf « Refuser » (après une réponse du demandeur, un renvoi ou une pause) |
| **En pause** | Support | « Reprendre » → En cours, à la main ou à la date prévue |
| **En attente du demandeur** | demandeur | « Répondre » (réponse) → En cours |
| **Résolu** | demandeur | « Confirmer et clore » → Clos · « Retour au Support » (motif) → En cours |
| **Clos**, **Annulé** | — | états finaux. Clos est l'état « terminé » de la facturation. |

Une seule exception, voulue : **le demandeur peut retirer sa demande tant qu'elle est Nouveau** (le Support n'a pas
encore répondu), avec un motif.

Le Support ne reprend jamais la main dans le circuit normal : « Reprendre » ne part plus que d'une pause. Reprendre
la main quand c'est le tour du demandeur, ou clore sans lui, ce sont des forçages (§3.4).

### 3.2 Messages, notes, modifications

- **Un message va toujours avec un passage** (16/10/2026) : il n'y a pas de message à part, ni « Envoyer le message ».
  - Le demandeur, à son tour : « Répondre » ; sur un ticket résolu, « Confirmer et clore » (avec un mot facultatif) ou
    « Retour au Support » (avec ce qui ne va pas).
  - Le Support, à son tour : « Demander des précisions » ou « Marquer comme résolu », qui passent la main au demandeur.
    Un point d'étape (« je regarde, je reviens vers vous ») s'écrit en **note**.
  - Un brouillon ne reçoit pas de message : sa demande (titre, description) est le premier message.
  - Sur un ticket, le **temps n'a pas de texte** : il porte sur un message ou une note existants, ou sur la fiche
    entière (§10).
- **À part, à tout moment**, chacun a :
  - les **notes** (droit `note`), lues de tous et qui préviennent les destinataires comme un message ;
  - la **modification de son dernier message** (ci-dessous) ;
  - **« Relancer »** (droit `remind`) : un message marqué relance, adressé à qui a le tour. C'est le seul message sans
    passage.
- **Modifier un message** : seulement **le dernier message du ticket, par son auteur**, que ce soit son tour ou non.
  Dès que quelqu'un d'autre écrit un message, plus personne ne touche aux messages précédents. Même règle pour tous,
  et pour les tâches projet. Une note reste modifiable par son auteur.
- **La demande** compte comme le premier message : son auteur la modifie (titre, description, priorité, pièces
  jointes) en brouillon, puis après la transmission **tant qu'aucun message n'a suivi**. Le Support, à son tour,
  retouche le titre et la priorité, jamais la description.

### 3.3 Prendre la main (côté client)

Quand c'est le tour du demandeur (En attente du demandeur, Résolu), un autre **destinataire principal** du ticket,
correspondant de la même application, peut **« Prendre la main »** :

- il **devient le demandeur** : c'est lui qu'on attend, qui répond, confirme ou renvoie ; si le Support lui répond de
  nouveau, c'est encore lui qu'on attend ;
- l'état ne change pas ; l'historique l'écrit (« Benoît reprend la demande de Léo ») et l'ancien demandeur est
  prévenu ;
- l'ancien demandeur reste destinataire principal : il peut reprendre la main de la même façon ;
- jamais le Support ; jamais quand c'est le tour du Support ; aucun droit en plus. Le maître l'exécute, comme un
  passage.

### 3.4 Forçages

Des actions distinctes du circuit normal, chacune avec **son propre droit**, réservées au Support ou au Super Admin.
Rien n'empêche techniquement de les donner à un correspondant ; c'est à éviter.

| Forçage | Depuis | Vers | Droit |
|---|---|---|---|
| **Forcer la clôture** (motif obligatoire) | tout état ouvert après transmission : Nouveau, En cours, En pause, En attente du demandeur, Résolu | Clos | `force-close` |
| **Forcer le retour** (reprendre la main) | En attente du demandeur, Résolu | En cours | `force-return` (Support) |

Le planificateur garde ses passages : clôture automatique (§4), reprise d'une pause à sa date, publication programmée.
L'annulation par le Support « à tout moment » disparaît : il refuse un ticket Nouveau, ou force la clôture ensuite.

### 3.5 Ce qui ne change pas

- Toute transition enregistre l'ancien et le nouvel état, l'auteur, l'application d'origine, le motif et l'origine.
- **Brouillons du maître** : le maître prépare un ticket pour un client, au nom d'un de ses correspondants, invisible
  chez ce client tant qu'il est en brouillon ; il le publie tout de suite ou à une date.

---

## 3 bis. Migrations remises à plat (16/10/2026)

Rien n'est encore en usage réel, production comprise : plutôt qu'un empilement de corrections, le plugin a **un seul
jeu de migrations propres** (une par groupe de tables, au schéma final).

- Installation neuve : `vendor:publish --tag=filament-tma-migrations` puis `migrate`, comme avant.
- Installation existante : retirer de l'application les anciennes migrations `*tma*` publiées, publier les nouvelles,
  puis **`php artisan tma:reset-schema --force` avant `migrate`**. La commande supprime les tables du module et leurs
  lignes dans `migrations`, recrée le schéma, et garde par défaut les liaisons entre applications (applications
  clientes, membres du Support reliés, forfaits) ; `--all` vide tout. Tickets, tâches, messages et temps sont perdus.
- Les droits `tma.ticket.workflow.*` disparaissent : `permissions:sync`, puis donner `correspond`, `handle` et les
  forçages aux rôles.

---

## 4. Clôture automatique et courriels

Une commande planifiée (`tma:close-stale`) clôture les tickets où **la main est au demandeur** (En
attente du demandeur, À valider) et où **rien ne s'est passé** depuis un délai configurable :
**28 jours** par défaut. « Rien ne s'est passé » veut dire aucune entrée de chronologie et aucune
transition.

- La clôture est enregistrée comme une transition d'origine « planificateur », avec le motif
  « Clôture automatique sans réponse ».
- Seul le maître clôture : il fait autorité sur tout ticket transmis.
- Deux courriels sont prévus :
  - un **avertissement** avant la clôture (7 jours avant par défaut, désactivable) ;
  - un **avis de clôture**.
- **Chaque application notifie ses propres utilisateurs.** Le maître ne prévient pas directement
  un utilisateur de Desapp : il synchronise la transition, et Desapp envoie le courriel. Cela vaut
  pour toutes les notifications.

### Notifications et destinataires (15/10)

Décidé avec l'utilisateur :

- **Destinataires** sur chaque ticket et chaque tâche (`tma_recipients`) : un ou plusieurs **principaux**, prévenus
  tout de suite de ce qui s'y passe ; un ou plusieurs **secondaires**, qui ne l'apprennent que dans le bilan ; les
  autres, rien. « Tout le monde » coche d'un clic toutes les personnes possibles. Modifiables tout au long de la vie
  du ticket. Remplace les « personnes qui suivent » et les modes par sorte.
- **Principal : courriel, cloche de Filament, ou les deux, au choix de chacun** (« Mes notifications »). La cloche
  suit le principe de Filament : une seule notification Laravel `mail` + `database` (`toDatabase()` →
  `Filament\Notifications\Notification::getDatabaseMessage()`).
- **Automatismes** : l'auteur toujours principal ; à l'envoi d'un ticket (confier) au moins un principal dans
  l'équipe qui reçoit, et le contact au Support choisi à la transmission (16/10/2026) ; à la publication d'une tâche, au
  moins un principal chez le client.
- **Deux applications** : le client ne nomme que ses utilisateurs, et le Support par son **compte passerelle**
  (`api.master_user`), d'office à la transmission ; le maître le traduit en ses destinataires par défaut
  (`recipients.support_defaults`, charles@notilac.fr). Le maître nomme ses membres et les utilisateurs du client.
  Des membres du Support reliés chez le client peuvent être nommés un par un si l'application le permet
  (`recipients.support_members`, charles@notilac.fr, comptes Super Admin).
- **Correspondants du Support** (15/10, simplifié le 16/10) : seuls les correspondants (`tma.ticket.correspond`)
  font des demandes et sont adressables des deux côtés (`GET users`). Les « internes » et l'« équipe EDM » qui traitait
  dans l'application disparaissent avec le traitement interne (§2).
- **Rouvrir une tâche** (15/10) : terminée ou annulée, elle repart En cours (`TaskMoves\ReopenTask`, droit
  `tma.task.workflow.reopen`), dans le maître ou par un membre du Support depuis l'application cliente (le maître
  exécute, par la route agent).
- **Le bilan** (récapitulatif existant) : « Ce qui vous attend » puis « Depuis le dernier envoi ». **Rappel** d'un
  ticket sans changement d'état depuis 7 jours à qui a la main, dans le bilan. **À la main** : « Envoyer maintenant »
  (`notify`), « Relancer » (`remind`, toujours tout de suite, par courriel au moins), `tma:notify --stale|--grouped`.


### Courriels : un ticket engage, une tâche informe (20/10)

Décidé avec l'utilisateur (son tableau du 20/10), remplace « principal : courriel, cloche ou les deux » :

- **Ticket** (on attend une action) : un principal voit tout dans la **cloche**, qu'on ne coupe pas. Quand le ticket
  l'attend (création : le Support si le client crée, au moins un client si le Support crée ; puis à chaque fois que la
  main lui revient), c'est une **action** : un courriel s'il a choisi l'**e-mail direct**, sinon son **bilan du
  matin**. Il garde au moins l'un des deux. D'origine : cloche + bilan du matin, sans e-mail direct.
- Les **fins de ticket** (clos, refusé, retiré, clos d'office ou automatiquement) : cloche + bilan du matin. Le reste
  (note, pause, prise de main…) : la cloche.
- **Relance faite à la main** : toujours un courriel. **Relances automatiques** (rappel de ce qui ne bouge plus) :
  le bilan seulement. L'avertissement de clôture est une action (e-mail direct, sinon bilan).
- **Tâche** (on n'attend rien du client) : chez le client, la cloche et le **bilan de la semaine** (le lundi, option
  oui/non, oui d'origine) pour la publication, une modification, le temps passé (une tâche a un temps, pas de prix),
  un message, la fin. Jamais de courriel, sauf **Informer par courriel** (le Support, à la main ; message marqué
  `meta.notify`). Le Support n'apprend d'une tâche que ce que le client y écrit, comme une action.
- **Question du Support sur une tâche (23/10/2026)** : elle engage, comme un ticket, les personnes à qui elle est
  posée (même en copie de la tâche) : cloche, puis e-mail direct ou bilan du matin (« À faire », avec son texte, tant
  qu'elle est ouverte ; signalée aussi dans le bilan de la semaine). Les autres destinataires : pour information. Le
  courriel prend la couleur et l'« À faire » d'un ticket. Voir §10.
- **Secondaire** : seulement le bilan, en bas, sous « En copie ».
- Courriels : gabarits propres (logo de l'application, couleur primaire et « À faire » pour un ticket, secondaire et
  « Pour information » pour une tâche ; tableaux et styles en ligne pour les messageries), objet qui dit ce qui se
  passe. Code : `Support\Notifier`, `Enums\NoticeTone`, `Support\NoticeChannels`, `Support\MailBrand`,
  `resources/views/mail`, `Notifications\DailyDigest` / `WeeklyDigest`, `tma:digest --weekly`.
- Corrigé le même jour : le maître ne prévenait pas le Support d'une demande transmise (`Api\Master\TicketController`).

---

## 5. Utilisateurs entre applications

- Le maître interroge le client pour connaître ses utilisateurs : `GET /api/tma/v1/users` (le
  « getTmaUsers »). La réponse ne contient que les correspondants du Support (§2).
  - Les réponses sont mises en cache côté maître (durée configurable, 1 h par défaut), avec un
    bouton de rafraîchissement.
- Pas de table d'utilisateurs copiée. Chaque élément écrit par un utilisateur distant (ticket,
  commentaire, note, transition) garde une **référence d'auteur** : application, id distant, plus
  une copie du nom et de l'email au moment de l'écriture. L'historique reste lisible même si
  l'utilisateur disparaît de l'autre côté.
- Les deux côtés lisent, créent et modifient selon le tour (§3). Seul le maître :
  - saisit du temps (heures ou jours, facturables ou non) sur les tickets et les tâches projet ;
  - crée des tâches projet ;
  - fait des brouillons invisibles et programme leur publication.

---

## 6. Chronologie : commentaires, notes, échanges

| Type | Visibilité | Synchronisation |
|---|---|---|
| **Commentaire** | les deux parties | automatique |
| **Note** | les deux parties (depuis le 14/10/2026 ; avant, l'application où elle était écrite, jusqu'au partage) | automatique |
| **Réponse** | celle du message auquel elle répond | celle du message auquel elle répond |
| **Événement** (transition, temps, synchro, pièce jointe) | selon le type | avec la transition qui le porte |
| **Résumé d'intervention** | les deux parties | automatique |

- Un message peut recevoir des **réponses**, sur un seul niveau. Cela donne le mini échange
  question / réponse avec images, sur les tickets comme sur les tâches projet.
- Sur une **tâche projet**, le client ne peut ni créer de tâche ni changer son état. Il peut
  commenter, ajouter des notes et des images, et répondre.
- Hors de son tour, on écrit des notes, pas des messages (§3.2).
- **Notes (14/10/2026)** : une note commente un message de premier niveau ou le ticket entier, quelle que soit la
  partie qui a la main. Numérotée par ticket (n° 1, 2…, un numéro supprimé n'est pas redonné), avec texte et images.
  Quiconque a le droit `note` l'archive ou la ressort ; son auteur seul la modifie ou la supprime. Elle s'ouvre en
  fenêtre : la grande fenêtre montre le message (ou la demande) et ses notes à droite ; l'onglet « Notes » du volet
  les liste toutes. Le partage (`share-note`, `TMA_NOTES_SHARING`) disparaît.
- **Modifier son message** (16/10/2026, remplace la règle du 14/10) : seulement le dernier message du ticket ou de
  la tâche, par son auteur (§3.2).
- **Note pour soi (14/10/2026)** : une note est lue de tous par défaut ; son auteur peut la garder pour lui
  (« Visible de moi seul », `private`), à l'écriture ou après, et la montrer plus tard. Elle voyage comme les autres
  (son auteur la retrouve des deux côtés), garde son numéro, ne change pas la dernière activité ; seul son auteur
  l'archive.

---

## 7. API et synchronisation

- **Les deux applications exposent une API** sous `/api/tma/v1`, documentée dans `docs/API.md`.
- **Authentification** : jeton Passport d'un compte de service de chaque côté, émis par
  filament-permission-manager. Chaque route est protégée par `fpm.api.validate` et
  `fpm.api.permission`.
  - Côté maître, le compte de service d'un client est **lié à une instance** : le maître déduit
    l'application cliente du jeton, jamais du contenu de la requête. C'est ce qui isole les clients
    entre eux.
  - Les jetons de l'autre côté ne sont jamais dans un fichier versionné. Ils sont dans `.env` côté
    client, chiffrés en base (cast `encrypted`) côté maître.
- **Client → maître** (transmission, commentaires, transitions du demandeur) : appel
  **synchrone**. En cas d'échec, l'opération est **annulée** avec un message d'erreur (décision
  Q4).
- **Maître → client** : **file d'attente avec tentatives de renvoi**, pour qu'une indisponibilité
  du client ne bloque pas le travail dans le maître. Après plusieurs échecs, l'élément passe en
  « synchro en erreur », avec alerte et relance manuelle.
- **Ce qui est synchronisé** :
  - les transitions ;
  - les commentaires, les notes et les réponses, avec leurs modifications (archivage et suppression des notes) ;
  - les pièces jointes de ces éléments ;
  - les champs du ticket modifiés par la partie qui a la main ;
  - les **totaux de temps** du ticket (global et par mois), envoyés avec les synchros. Les lignes
    de temps restent dans le maître.
- **Idempotence** : chaque objet a un ULID global, et chaque message une clé d'idempotence.
  Rejouer un message ne crée pas de doublon.
- **Conflit** : sur un ticket du centre de service, la version du maître gagne. Le conflit est
  journalisé et le client est réaligné (CDC §15.5).
- En développement, les deux Sail doivent se joindre (réseau Docker partagé, comme `shared-db`).

---

## 8. Commande artisan

Une famille de commandes `tma:*` est disponible dans **les deux** applications. Elles sont pensées
pour être lancées par une IA (sortie `--json`, aucune question interactive quand les options sont
fournies).

```bash
php artisan tma:list    [--client=] [--app=] [--state=] [--json]
php artisan tma:show    {ref} [--json]
php artisan tma:log     {ref} --hours=2 [--entry=] [--summary="…" (tâche)] [--date=] [--non-billable] [--json]
php artisan tma:comment {ref} --body="…" [--note] [--json]
```

- **Protection** : chaque appel exige un jeton personnel (`TMA_CLI_TOKEN` dans `.env`, ou
  `--token`), jamais versionné.
  - Le jeton désigne un utilisateur, et ses permissions sont contrôlées comme dans l'interface.
  - Chaque appel est audité avec l'origine « commande ».
- **Dans le maître**, la commande agit directement.
- **Dans un client**, elle appelle l'API du maître. Comme le temps ne se saisit que dans le maître,
  `tma:log` lancé depuis Desapp agit en tant qu'**utilisateur maître** : le jeton est un jeton de
  Filbreeze.
- La durée et le résumé sont enregistrés séparément (une saisie de temps et une entrée « Résumé
  d'intervention »).

---

## 9. Facturation (lot 3)

- Un **contrat et son trait** (par exemple `ProvidesInvoiceItems`) exposent ce qui reste à
  facturer (tickets clôturés, tâches terminées, temps facturable par mois), à la manière de l'import
  d'un devis dans `InvoiceResource`. La facture reçoit des items générés à partir de ces éléments.
- **Dès le lot 1**, chaque saisie de temps a un lien de facturation (vide au départ). C'est lui qui
  porte le verrou du CDC §10.3 : une saisie liée à une facture n'est plus modifiable.
- Le reste (facturation partielle, avoirs, états calculés) est précisé au lot 3.

---

## 10. Tâches projet

Le workflow du CDC §8 est inchangé : **Brouillon** (invisible chez le client, publication
programmable) → **En cours** (mise à disposition et synchronisée) → **Terminée**, plus **Annulée**.

Elles sont créées uniquement par le maître. Le client y participe par la chronologie (§6).

Décisions du 14/10/2026 :

- **Projets** : un projet a un titre (et une description), une application destinataire et N tâches ; une tâche
  appartient à un projet, dont elle prend l'application. Le temps d'un projet est la **somme du temps de ses
  tâches** : la facturation par projet s'appuiera dessus (§9). Le projet voyage avec ses tâches publiées.
  Revu le 23/10/2026 (demande de l'utilisateur : tout enregistrement part chez l'autre) : le projet part aussi seul,
  dès sa création, à chaque changement et à sa suppression, même sans tâche publiée (`POST sync`, `kind` = `project`).
- **Modifier une tâche** : le maître modifie titre, description, dates et projet à tout moment, même terminée.
- **Messages** : une tâche terminée se commente encore (surtout par le client) ; une tâche annulée n'accepte plus
  que des notes.
- **Ticket depuis une tâche** : « Créer un ticket » ouvre un ticket de TMA prérempli, lié à la tâche (`task_id`,
  `task_ulid` dans l'API), des deux côtés.
- **Temps sur un message** : une saisie de temps peut porter sur un message ou une note de la fiche (`entry_id`).
- **Le temps n'a plus de texte (15/10/2026)** : la colonne `description` des saisies disparaît. Une saisie porte sur
  un message ou une note existants, ou sur un **nouveau message** écrit avec elle dans l'historique de la fiche
  (ticket ou tâche), ou, sans l'un ni l'autre, sur la fiche entière. Même chose pour un membre du Support depuis une
  application cliente, et pour `tma:log` (`--summary` écrit le message, `--entry` désigne un message existant).
  **Sur un ticket** (16/10/2026), plus de texte avec le temps : il porte sur un message ou une note existants, ou sur
  la fiche entière ; le nouveau message reste possible sur une tâche projet.
  Au-dessus de la durée, des raccourcis la remplissent (10, 15, 20, 30, 45 min ; 1, 2, 3 h ; ½ j ; 1 j).
- **Supprimer une tâche (15/10/2026)** : dans le maître seulement (droit `tma.task.delete`), quel que soit son état,
  depuis la liste (icône, action groupée) ou la fiche, après un avertissement qui dit ce qui part. La tâche part avec
  ses messages, ses notes et son temps ; publiée, elle disparaît aussi chez le client (l'état poussé porte
  `deleted_at`). Suppression douce : numéro jamais redonné, trace dans l'audit, rien ne la défait dans l'interface.
  Refusée si du temps est rattaché à une facture. Les tickets ouverts depuis elle restent, sans lien.
- **Publication (15/10/2026)** : sans date, une tâche part chez le client dès l'enregistrement ; avec une date, elle
  reste en brouillon et compte comme publiée **à l'heure dite** (date de publication, passage, dernière activité),
  quel que soit le passage du planificateur, qui vérifie désormais chaque minute. En tête de l'historique, la tâche
  se lit comme un premier message (auteur, heure de publication, description).
- **Brouillon de tâche (15/10/2026)** : rien ne s'y écrit (pas de message) avant sa publication ; sa description est
  le premier message.
- **Destinataires d'une tâche (15/10/2026)** : comme un ticket (§4) ; pour l'enregistrer avant sa publication, ou la
  publier, au moins un principal chez le client.
- **Jeu d'exemples** : `tma:demo` (voir le README), rejouable, par le vrai workflow.
- **Pièces jointes (23/10/2026)**, demande de l'utilisateur (Markdown, PowerPoint, PDF, Excel sans macro) : PDF, docx,
  xlsx, pptx étaient déjà dans la liste blanche ; `text/markdown` s'y ajoute. Contrôle unique, quel que soit le chemin
  (formulaires, historique, autre application, `tma:*`) : `Support\Attachments`. Le type se détecte sur le contenu et
  l'extension doit aller avec ; un `.md` est du Markdown même si son texte se détecte en HTML (enregistré en
  `text/markdown`, toujours téléchargé) ; un document Office/OpenDocument est refusé s'il contient une macro
  (`vbaProject.bin`, type `macroEnabled`, `Basic/`, `Scripts/`), un contrôle ActiveX, un objet OLE incorporé ou une
  relation externe active (modèle, objet, sous-document), ou s'il dépasse `attachments.max_unpacked_mb` décompressé.
  Les champs n'utilisent plus la règle `mimetypes` de Filament (elle refusait ce Markdown) mais `Attachments::rule()`
  ; la collection medialibrary, `acceptsFile()`. Les fichiers d'un formulaire de ticket ou de tâche gardent leur nom
  d'origine simplifié. Un ZIP reste accepté sans regarder dedans ; un PDF n'est pas inspecté (il se télécharge, avec
  `nosniff` et une CSP `sandbox`).
- **Questions du Support (23/10/2026)**, décidé avec l'utilisateur : une tâche informe, une question engage. Plutôt
  qu'un ticket ouvert à côté (son temps irait au forfait de TMA), deux sortes de messages sur une tâche : l'information
  (le message d'avant, « Informer par courriel » compris) et la **question**, posée par le Support dans le maître, à des
  personnes du client choisies parmi les destinataires de la tâche (d'office ses principaux). C'est un message de
  premier niveau marqué `meta.question` (`to` : leurs identifiants dans l'application cliente ; `withdrawn_at` quand
  le Support la retire). Elle est ouverte jusqu'à la **première réponse de quelqu'un du client** (une réponse au
  message, `parent_ulid`), ou son retrait ; ni tour, ni relance, ni clôture automatique, la tâche ne change pas
  d'état, le temps reste au projet. Tout voyage avec la chronologie (rien de plus dans l'API) ; le client repère un
  retrait dans l'état recopié. Le client ne pose pas de question : tout ce qu'il écrit sur une tâche est déjà une
  action pour le Support. Interface : « Poser une question » dans la zone d'écriture, « Répondre » et « Retirer la
  question » sous elle ; bilan du matin (« Les questions du Support sur vos projets »), bilan de la semaine, onglet
  « Projets » du tableau de bord. Code : `Workflow\Questions`, `Filament\Actions\QuestionActions`,
  `Support\Notifier`, `Support\Digest`. Le même jour, la publication d'une tâche se dit « Le Support a ajouté cette
  tâche au projet » (et non plus « publié une tâche pour vous » : la tâche est le travail du Support).
- **Fuseaux** : les dates s'enregistrent en UTC et s'affichent dans le fuseau du lecteur (`users.timezone` s'il
  existe, sinon celui de Filament), y compris dans les vues propres au module et les courriels (`Support\Dates`).

---

## 11. Découpage en lots

Chaque étape importante fait l'objet d'un commit.

| Lot | Contenu | Testable dans |
|---|---|---|
| **0** | Dépôt du plugin, CDC, addendum | — |
| **1a** | Squelette du package, configuration (mode, instance), modèles et migrations communs, tickets, workflow, chronologie (commentaires, notes, réponses, pièces jointes), ressource Filament, droits | Desapp seul, en **traitement local** |
| **1b** | API des deux côtés et `docs/API.md`, transmission au centre de service, utilisateurs distants, synchro maître → client en file, ressources du maître, saisie de temps | Filbreeze + Desapp |
| **1c** | Clôture automatique, courriels d'avertissement et de clôture, reprise des pauses à date | les deux |
| **1d** | Commandes `tma:*` | les deux |
| **2** | Tâches projet, brouillons et publication programmée, forfaits et seuils, récapitulatif quotidien | les deux |
| **3** | Facturation (`ProvidesInvoiceItems`), nettoyage des fichiers, audit complet | les deux |

---

## 12. Avancement et valeurs retenues sans décision explicite

Lots 1 et 2 réalisés (branche `lot-2` du plugin, `tma` de Filbreeze). Choix faits en l'absence de consigne, à
valider :

- **Forfaits** : seul le temps **facturable** des **tickets** consomme le forfait (`allowances.consume`) ; seuils
  80/90/100/110 % ; le dépassement ne bloque pas (`allowances.block_overage`) ; les seuils préviennent les adresses
  de `allowances.notify`.
- **Notifications** : aucune notification immédiate par défaut, tout passe par le récapitulatif quotidien (jours
  ouvrés, 8 h). La clôture automatique prévient toujours.
- **Brouillons du maître** : la publication met le ticket en **Nouveau** ; son auteur en est le contact au Support.
- **Tâches projet** : numérotées par le maître (`T-0007`) ; un résumé d'intervention s'ajoute aussi à un objet
  terminé (temps reporté après coup).
- **Tâches et commandes** : `tma:list` et l'API agent couvrent tickets et tâches (`--type`).

## 13. Points encore ouverts

Valeurs par défaut retenues en attendant une décision :

1. **Temps visibles côté client** : les totaux seulement (global et par mois). Le détail des lignes
   pourra être ouvert plus tard, par configuration.
2. **Réouverture d'un ticket clôturé** : impossible. On crée un nouveau ticket lié à l'ancien.
3. **Délai d'avertissement** avant clôture automatique : 7 jours.
4. **Durée du cache des utilisateurs distants** : 1 heure.
