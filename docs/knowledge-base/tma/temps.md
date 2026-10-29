---
title: Le temps passé
icon: heroicon-o-clock
order: 50
---

# Le temps passé

Le temps se saisit **chez le Support seulement**, sur un ticket ou une tâche projet. Les applications
clientes en voient le total, et le détail par mois.

Partout (fiches, tableau de bord, courriels, bilans), il s'affiche dans une seule unité, avec deux décimales au plus :

- le temps d'un **ticket**, d'une **tâche** ou d'une **saisie** s'adapte à sa durée : en **jours** dès une journée
  (« 1,5 j »), en **heures** en dessous (« 2,5 h »), en **minutes** jusqu'à 30 minutes (« 30 min ») ;
- une **somme** (un projet, la TMA, un forfait, le total d'un mois) est en **jours** : « 2,55 j », « 0,5 j »
  (`time.display_unit` la passe en heures, `hours`, ou en minutes, `minutes`).

Une journée vaut 7 heures (réglable : `time.day_minutes`).

## Le bloc « Temps passé »

Dans le volet de droite de la fiche, un seul bloc : les **totaux** en tête (« Cette tâche : 6,02 h · Tout le projet :
1,71 j », « Ce ticket : 2 h »), puis, chez le Support, **une ligne par saisie** : qui, le jour (jour/mois), la durée, et
les icônes **Modifier** et **Supprimer** ; dessous, en petit, le message ou la note sur lequel porte le temps.

- Chez le Support, il vient **en tête** du volet.
- Pour qui n'a pas le droit de saisir du temps, et dans l'application cliente, il est **en lecture** : les totaux
  seuls, en bas du volet. Un membre du Support y a le même bloc que chez le Support, lu chez lui : le détail, **Saisir
  du temps**, modifier, supprimer (voir [Les membres du Support](#modal-tma.agents)).

## Saisir du temps

Dans ce bloc : **Saisir du temps** :

- les **raccourcis** (10 min, 15 min, 20 min, 30 min, 45 min, 1 h, 2 h, 3 h, ½ j, 1 j) remplissent la durée d'un
  clic (½ j donne 3 h 30) ;
- **Le** : le jour de l'intervention ; c'est lui qui range le temps dans son mois ;
- **Durée** : des **jours**, des **heures** et des **minutes** (« 2 j 2 h 5 min », chacun facultatif) ; le total
  s'affiche dessous (« Soit 2,3 j », « Soit 2,5 h » ; une journée vaut 7 heures, réglable) ;
- **Sur** : un message ou une note de la fiche ; sur une tâche projet, aussi **un nouveau message** ;
- **Ce qui a été fait** (tâche projet, pour un nouveau message) : écrit dans l'historique de la tâche, avec le temps ;
- **Facturable** ou non.

Le temps ne porte pas lui-même de texte : ce qui a été fait se lit dans le message ou la note sur lequel il porte. Sur
un ticket, où un message va toujours avec un passage, on n'écrit rien avec le temps : pour le dire, écrivez d'abord une
note, puis rattachez-y le temps. Sans message ni note choisis, il porte sur la fiche entière.

Une intervention = une ligne. Un ticket ouvert de janvier à mars a donc son temps réparti sur chacun de ces mois.

Le temps se saisit aussi sur un ticket terminé : on reporte souvent une intervention après coup.

## Modifier ou supprimer

Tant qu'une saisie n'est pas **rattachée à une facture**, elle se modifie et se supprime. Une fois rattachée, elle
est **verrouillée** (mention « Facturée » dans la liste), quel que soit l'état de la facture : il faut d'abord défaire le
rattachement.

## Le forfait

Le temps **facturable** des tickets consomme le forfait du client. Si le forfait est réglé pour bloquer les
dépassements, une saisie qui le ferait dépasser est refusée. Voir [Les forfaits](#modal-tma.forfaits).

## Depuis un assistant IA

La commande `tma:log` saisit du temps sur un message ou une note existants, ou sur la fiche entière ; sur une tâche
projet, aussi sur un message qu'elle écrit (ce qui a été fait).
Voir [Les commandes](#modal-tma.commandes).

## En détail

- [Saisir une durée](#modal-tma.temps.saisie)
- [Temps facturable](#modal-tma.temps.facturable)
