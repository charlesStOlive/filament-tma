---
title: Rappels et notifications
icon: heroicon-o-bell
order: 80
---

# Rappels et notifications

## Le récapitulatif quotidien

Chaque jour ouvré à 8 h, chacun reçoit **un seul courriel** avec ce qui l'attend :

- ses demandes qui attendent **sa réponse** ou **sa validation** ;
- les tickets soumis que personne n'a pris et qu'il peut **prendre en charge** ;
- ce qu'il **traite** : tickets en cours, tâches projet qui lui sont confiées ;
- ses pauses qui **reprennent aujourd'hui**.

Ce qui attend depuis plus de 5 jours, ou dont l'échéance est passée, est signalé **en retard**. Personne ne reçoit
de courriel vide. L'heure, les jours et le fuseau se règlent.

## Les notifications immédiates

Par défaut, il n'y en a pas : tout passe par le récapitulatif. L'application peut en activer certaines :

| Passage | Qui est prévenu |
| --- | --- |
| Demander une réponse, Soumettre à validation, Prendre en charge, Publier | le demandeur |
| Soumettre (avec un intervenant), Répondre, Rouvrir | l'intervenant |

L'auteur du passage n'est jamais prévenu de son propre geste.

## Ce qui prévient toujours

- **La clôture automatique** : le demandeur est averti 7 jours avant, puis à la clôture.
- **Les seuils des forfaits** : les adresses configurées. Voir [Les forfaits](#modal-tma.forfaits).

## Chacun chez soi

Chaque application prévient **ses propres utilisateurs**. Quand le centre de service pose une question sur un ticket
de Desapp, c'est Desapp qui envoie le courriel à son demandeur, à la réception.

## Les tâches automatiques

| Tâche | Quand | Ce qu'elle fait |
| --- | --- | --- |
| Clôture automatique | chaque jour à 7 h | avertit puis clôture les tickets sans réponse du demandeur |
| Reprise des pauses | tous les quarts d'heure | reprend les tickets en pause arrivés à leur date |
| Publications programmées | toutes les 5 minutes | publie les tâches et les tickets préparés arrivés à leur date |
| Récapitulatif | jours ouvrés à 8 h | envoie le récapitulatif |
