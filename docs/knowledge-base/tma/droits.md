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
| `comment` | écrire des messages et répondre |
| `note` | écrire des notes et les archiver (tout le monde sur le ticket les lit) |
| `time` | saisir et modifier du temps (Support) |
| `sync` | renvoyer un ticket ou une tâche à son application (Support) |
| `remind` | relancer qui a la main sur un ticket (tickets) |
| `notify` | envoyer maintenant les notifications groupées en attente (tickets) |
| `workflow.{passage}` | faire ce passage : `workflow.take-charge`, `workflow.validate`… |

La famille **Passages du ticket** (`tma.ticket.workflow.*`) regroupe un droit par passage. Un rôle de l'équipe qui
traite reçoit par exemple `take-charge`, `request-answer`, `pause`, `resume`, `submit-for-validation`, `close`,
`refuse` ; un rôle de demandeur `submit`, `answer`, `validate`, `reopen`, `cancel`.

**`tma.ticket.workflow.transmit`**, affiché **Correspondant du Support : écrire et transmettre au Support**, fait
d'une personne un **correspondant du Support** : ses demandes partent au Support (elle ne voit pas « Confier à
l'équipe EDM »), elle répond au Support pour un demandeur, et le Support peut la nommer demandeur d'un ticket préparé
ou destinataire. Sans ce droit, une personne est **interne** : elle confie ses demandes à l'équipe EDM et le Support
ne la voit pas. Par exemple, chez Desapp : `support_equipe_edm` (traite et correspond), `support_demandeur`
(correspond), `support_interne` (interne). Voir [Comprendre le module](#modal-tma.comprendre).

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
