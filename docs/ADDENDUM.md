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
| Workflow (§7) | `charlesstolive/filament-state-fusion-enhanced` (sur `spatie/laravel-model-states`) | Les workflows sont des états spatie avec les actions state-fusion. À installer dans Desapp. |
| Pièces jointes (§17) | `spatie/laravel-medialibrary` présent dans les deux applications | Pièces jointes et images via medialibrary. |

Package : `charlesstolive/filament-tma`, dans `packages_filament/filament-tma`.

---

## 2. Rôles : demandeur et intervenant

Un ticket a toujours deux parties :

- le **demandeur** : celui qui a ouvert la demande, côté application source ;
- l'**intervenant** : celui qui la traite.

Le **mode de traitement** dit qui est l'intervenant :

| Mode | Intervenant | Application qui fait autorité |
|---|---|---|
| **Local** | un utilisateur de l'application source | l'application source |
| **Centre de service** | l'équipe du maître | le maître |

Le traitement local est **primordial** : c'est un circuit complet, pas une étape avant transmission.
Certains utilisateurs clients n'auront **que** ce circuit (aucun droit de transmission). Ils ne
remontent pas au maître (voir §5).

Un ticket local peut être transmis au centre de service à tout moment avant sa clôture
(« Transmettre au centre de service »). L'inverse n'existe pas.

Règle générale : **quand la main est à l'autre partie, on ne peut plus qu'ajouter** (commentaires,
notes, pièces jointes). On ne modifie plus les champs du ticket et on ne déclenche pas de transition
réservée à l'autre partie.

---

## 3. Workflow des tickets

Huit états. Ils remplacent les onze du CDC §7.2 : « Soumise » et « À qualifier » sont fusionnés
dans **Soumis**, « À vérifier » et « Résolue » dans **À valider**, et « Refusée » devient un
**Annulé** avec motif.

| État | La main est à | Transitions |
|---|---|---|
| **Brouillon** | son auteur | « Soumettre » (local, à un intervenant choisi) → Soumis · « Transmettre au centre de service » → Soumis · « Annuler » → Annulé |
| **Soumis** | l'intervenant | « Prendre en charge » → En cours · « Refuser » (motif obligatoire) → Annulé · le demandeur peut « Annuler » tant que personne n'a pris en charge |
| **En cours** | l'intervenant | « Demander une réponse » (question obligatoire) → En attente du demandeur · « Mettre en pause » (date obligatoire) → En pause · « Soumettre à validation » → À valider |
| **En attente du demandeur** | le demandeur | « Répondre » (réponse obligatoire, par le demandeur) → En cours · « Reprendre » (par l'intervenant) → En cours · « Clôturer sans réponse » (par l'intervenant ou automatiquement, voir §4) → Clôturé |
| **En pause** | l'intervenant | « Reprendre » → En cours, à la main ou automatiquement à la date prévue |
| **À valider** | le demandeur | « Valider » → Clôturé · « Rouvrir » (motif obligatoire) → En cours · clôture par l'intervenant ou automatique (voir §4) → Clôturé |
| **Clôturé** | — | état final. C'est l'état « terminé » au sens de la facturation (CDC §14.4). |
| **Annulé** | — | état final, avec auteur et motif. |

Compléments :

- « Transmettre au centre de service » est aussi possible depuis Soumis, En cours et En attente du
  demandeur tant que le ticket est local : l'intervenant local escalade. Le ticket repart en
  **Soumis**, en mode centre de service.
- L'intervenant peut annuler, avec motif, depuis tout état non final.
- Toute transition enregistre l'ancien et le nouvel état, l'auteur, l'application d'origine, le
  motif et l'origine (interface, API, commande, planificateur).
- Un commentaire ou une note ne change jamais l'état (CDC §9.4).
- **Brouillons du maître** : le maître peut créer un ticket pour un client, invisible chez ce
  client tant qu'il est en Brouillon. Il peut le publier tout de suite ou **programmer** sa
  publication. Le client peut faire des brouillons, mais pas programmer de publication.

---

## 4. Clôture automatique et courriels

Une commande planifiée (`tma:close-stale`) clôture les tickets où **la main est au demandeur** (En
attente du demandeur, À valider) et où **rien ne s'est passé** depuis un délai configurable :
**28 jours** par défaut. « Rien ne s'est passé » veut dire aucune entrée de chronologie et aucune
transition.

- La clôture est enregistrée comme une transition d'origine « planificateur », avec le motif
  « Clôture automatique sans réponse ».
- Chaque application ne clôture que les tickets dont elle a l'autorité : le maître ceux du centre
  de service, l'application source ses tickets locaux.
- Deux courriels sont prévus :
  - un **avertissement** avant la clôture (7 jours avant par défaut, désactivable) ;
  - un **avis de clôture**.
- **Chaque application notifie ses propres utilisateurs.** Le maître ne prévient pas directement
  un utilisateur de Desapp : il synchronise la transition, et Desapp envoie le courriel. Cela vaut
  pour toutes les notifications.

---

## 5. Utilisateurs entre applications

- Le maître interroge le client pour connaître ses utilisateurs : `GET /api/tma/v1/users` (le
  « getTmaUsers »). La réponse ne contient que les utilisateurs qui ont des droits sur le circuit
  centre de service.
  - Les utilisateurs **local uniquement** sont exclus par défaut. Ce filtre se lève par un
    paramètre explicite.
  - Les réponses sont mises en cache côté maître (durée configurable, 1 h par défaut), avec un
    bouton de rafraîchissement.
- Pas de table d'utilisateurs copiée. Chaque élément écrit par un utilisateur distant (ticket,
  commentaire, note, transition) garde une **référence d'auteur** : application, id distant, plus
  une copie du nom et de l'email au moment de l'écriture. L'historique reste lisible même si
  l'utilisateur disparaît de l'autre côté.
- Les deux côtés lisent, créent et modifient selon leurs droits et selon qui a la main. Seul le
  maître :
  - saisit du temps (heures ou jours, facturables ou non) sur les tickets et les tâches projet ;
  - crée des tâches projet ;
  - fait des brouillons invisibles et programme leur publication.

---

## 6. Chronologie : commentaires, notes, échanges

| Type | Visibilité | Synchronisation |
|---|---|---|
| **Commentaire** | les deux parties | automatique |
| **Note** | l'application où elle est écrite | **manuelle** : action « Partager » sur la note, des deux côtés |
| **Réponse** | celle du message auquel elle répond | celle du message auquel elle répond |
| **Événement** (transition, temps, synchro, pièce jointe) | selon le type | avec la transition qui le porte |
| **Résumé d'intervention** | les deux parties | automatique |

- Un message peut recevoir des **réponses**, sur un seul niveau. Cela donne le mini échange
  question / réponse avec images, sur les tickets comme sur les tâches projet.
- Sur une **tâche projet**, le client ne peut ni créer de tâche ni changer son état. Il peut
  commenter, ajouter des notes et des images, et répondre.
- Les notes correctives du demandeur pendant que la main est à l'intervenant sont des commentaires
  ou des notes partagées. Elles ne changent pas l'état.

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
  - les commentaires, les notes partagées et leurs réponses ;
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
php artisan tma:log     {ref} --hours=2 --summary="…" [--date=] [--non-billable] [--json]
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

## 12. Points encore ouverts

Valeurs par défaut retenues en attendant une décision :

1. **Temps visibles côté client** : les totaux seulement (global et par mois). Le détail des lignes
   pourra être ouvert plus tard, par configuration.
2. **Réouverture d'un ticket clôturé** : impossible. On crée un nouveau ticket lié à l'ancien.
3. **Délai d'avertissement** avant clôture automatique : 7 jours.
4. **Durée du cache des utilisateurs distants** : 1 heure.
