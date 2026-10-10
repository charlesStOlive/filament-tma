---
title: Le temps passé
icon: heroicon-o-clock
order: 50
---

# Le temps passé

Le temps se saisit **chez le Support seulement**, sur un ticket ou une tâche projet. Les applications
clientes en voient le total, et le détail par mois.

## Saisir du temps

Sur la fiche, onglet **Temps passé › Saisir du temps** :

- **Le** : le jour de l'intervention ; c'est lui qui range le temps dans son mois ;
- **Durée**, en minutes, heures ou jours (une journée vaut 7 heures, réglable) ;
- **Ce qui a été fait** ;
- **Facturable** ou non.

Une intervention = une ligne. Un ticket ouvert de janvier à mars a donc son temps réparti sur chacun de ces mois.

Le temps se saisit aussi sur un ticket terminé : on reporte souvent une intervention après coup.

## Modifier ou supprimer

Tant qu'une saisie n'est pas **rattachée à une facture**, elle se modifie et se supprime. Une fois rattachée, elle
est **verrouillée** (cadenas dans la liste), quel que soit l'état de la facture : il faut d'abord défaire le
rattachement.

## Le forfait

Le temps **facturable** des tickets consomme le forfait du client. Si le forfait est réglé pour bloquer les
dépassements, une saisie qui le ferait dépasser est refusée. Voir [Les forfaits](#modal-tma.forfaits).

## Depuis un assistant IA

La commande `tma:log` saisit du temps et enregistre à part un résumé d'intervention. Voir
[Les commandes](#modal-tma.commandes).

## En détail

- [Saisir une durée](#modal-tma.temps.saisie)
- [Temps facturable](#modal-tma.temps.facturable)
