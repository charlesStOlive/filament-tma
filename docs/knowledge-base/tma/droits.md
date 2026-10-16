---
title: Les droits
icon: heroicon-o-key
order: 100
---

# Les droits

Les droits du module se donnent dans l'écran des rôles, comme les autres. Toutes leurs permissions commencent par
`tma.`.

## Tickets (`tma.ticket.*`)

Sur un ticket, les droits disent **qui est qui** et ouvrent ce qui est **à côté du workflow**. Les passages normaux
(demander des précisions, répondre, confirmer…) ne sont **pas** des droits : ils suivent le tour (voir
[Qui doit agir ?](#modal-tma.comprendre.la-main)).

| Permission | Ce qu'elle permet |
| --- | --- |
| `viewany`, `view` | voir la liste, ouvrir une fiche |
| `create`, `edit`, `delete` | créer, modifier, supprimer (un brouillon) ; avec le tour et l'auteur de la demande en plus |
| **`correspond`** — **Correspondant du Support** (application cliente) | faire une demande et la transmettre, en être le demandeur ; le Support peut le nommer (demandeur d'un ticket préparé, destinataire) |
| **`handle`** — **Membre du Support** (maître) | traiter les demandes : demander des précisions, marquer comme résolu… à son tour ; être choisi comme contact |
| `force-close` — **Forçage** | forcer la clôture d'un ticket ouvert, hors du tour (voir [Les forçages](#modal-tma.tickets.forcages)) |
| `force-return` — **Forçage** | reprendre la main sans attendre le demandeur (Support) |
| `note` | écrire des notes et les archiver (tout le monde sur le ticket les lit) |
| `remind` | relancer qui a la main |
| `notify` | envoyer maintenant les notifications groupées en attente |
| `time` | saisir et modifier du temps (Support) |
| `sync` | renvoyer un ticket à son application (Support) |

Par exemple, chez Desapp : un rôle de correspondant (`correspond`, `note`, `remind`, la liste) ; chez Filbreeze, un rôle
du Support (`handle`, `note`, `remind`, `time`…) et, pour les administrateurs, les forçages.

## Tâches projet (`tma.task.*`)

| Permission | Ce qu'elle permet |
| --- | --- |
| `viewany`, `view`, `create`, `edit`, `delete` | la liste, la fiche ; créer, modifier, supprimer (Support) |
| `comment`, `note` | écrire des messages, des notes |
| `time`, `sync` | saisir du temps, renvoyer une tâche (Support) |
| `workflow.{passage}` | faire ce passage : `workflow.publish`, `workflow.complete`, `workflow.reopen`… |

## Les autres écrans

| Permission | Écran |
| --- | --- |
| `tma.instance.*` | Applications clientes (Support) |
| `tma.allowance.*`, `tma.allowance.reset` | Forfaits, et réinitialiser leurs seuils (Support) |
| `tma.audit.viewany` | Journal d'audit |
| `widgets.allowanceoverview.viewany` | la consommation du forfait, dans l'application cliente |

## Les droits ne font pas tout

Même avec tous les droits, Super Admin compris, on ne fait que ce que le tour permet : on ne répond pas, on ne
confirme pas à la place du demandeur, le Support ne reprend pas la main sans forçage, une application cliente ne saisit
pas de temps. Voir [Qui doit agir ?](#modal-tma.comprendre.la-main).
