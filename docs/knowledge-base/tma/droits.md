---
title: Les droits
icon: heroicon-o-key
order: 100
---

# Les droits

Les droits du module se donnent dans l'écran des rôles, comme les autres. Toutes leurs permissions commencent par
`tma.`.

## Tickets (`tma.ticket.*`) et tâches projet (`tma.task.*`)

| Permission | Ce qu'elle permet |
| --- | --- |
| `viewany`, `view` | voir la liste, ouvrir une fiche |
| `create`, `edit`, `delete` | créer, modifier, supprimer (un brouillon) |
| `comment` | commenter et répondre |
| `note` | lire et écrire les notes internes |
| `share-note` | partager une note avec l'autre partie |
| `time` | saisir et modifier du temps (Support) |
| `sync` | renvoyer un ticket ou une tâche à son application (Support) |
| `workflow.{passage}` | faire ce passage : `workflow.take-charge`, `workflow.validate`… |

La famille **Passages du ticket** (`tma.ticket.workflow.*`) regroupe un droit par passage. Un rôle de l'équipe qui
traite reçoit par exemple `take-charge`, `request-answer`, `pause`, `resume`, `submit-for-validation`, `close`,
`refuse` ; un rôle de demandeur `submit`, `answer`, `validate`, `reopen`, `cancel`.

**`tma.ticket.workflow.transmit`** a un sens de plus dans une application cliente : il désigne les utilisateurs qui
ont accès au **Support**. Les autres ne passent que par l'équipe du client, et le Support ne les voit pas.

## Les autres écrans

| Permission | Écran |
| --- | --- |
| `tma.instance.*` | Applications clientes (Support) |
| `tma.allowance.*`, `tma.allowance.reset` | Forfaits, et réinitialiser leurs seuils (Support) |
| `tma.audit.viewany` | Journal d'audit |
| `widgets.allowanceoverview.viewany` | la consommation du forfait, dans l'application cliente |

## Les droits ne font pas tout

Même avec tous les droits, on ne fait que ce que la situation permet : on ne modifie pas un ticket qui attend l'autre
partie, on ne confirme pas un ticket à la place du demandeur, une application cliente ne saisit pas de temps. Voir
[Comprendre le module](#modal-tma.comprendre).
