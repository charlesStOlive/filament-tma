---
title: Rappels et notifications
icon: heroicon-o-bell
order: 80
---

# Rappels et notifications

## Les destinataires

Chaque ticket et chaque tâche a ses **destinataires**, visibles dans le bloc **Destinataires** du volet :

- les **principaux** sont prévenus **tout de suite** de ce qui s'y passe : un changement d'état, un message, une
  note, une relance ;
- les **secondaires** l'apprennent dans leur **bilan** du matin ;
- les autres ne reçoivent rien.

On en choisit un ou plusieurs de chaque sorte, et on les change tout au long de la vie du ticket (**Modifier**) ;
**Tout le monde** coche d'un clic toutes les personnes possibles. Une personne ajoutée en est prévenue.

### Qui devient destinataire tout seul

| Moment | Qui devient principal |
| --- | --- |
| Création d'un ticket ou d'une tâche | son auteur, qui le reste toujours (et le demandeur d'un ticket préparé par le Support) |
| **Transmettre au Support** | qui transmet, et le Support (qui décide lui-même qui le reçoit) |
| **Transmettre au Support** (le contact) | le contact au Support choisi par le demandeur (d'office s'il n'y en a qu'un) |
| Publication d'une tâche | au moins une personne du client, à choisir (ou **Tout le monde**) |

### Qui peut être destinataire

- Dans l'application du client : ses **correspondants du Support**, et **le Support**, nommé par le compte passerelle
  de l'application, avec certains membres du Support si l'application le permet.
- Chez le Support : ses membres, et les **correspondants** du client.

Un destinataire **principal** du côté du client peut aussi **prendre la main** quand le demandeur est absent (voir
[Prendre la main](#modal-tma.tickets.question-reponse)) ; l'ancien demandeur en est prévenu.

Chaque application prévient **ses propres utilisateurs** : quand le Support écrit sur un ticket de Desapp, c'est
Desapp qui prévient ses destinataires, à la réception.

## Courriel, cloche, bilan

Un destinataire principal est prévenu par **courriel**, dans la **cloche** de l'application (les notifications en
haut de l'écran), ou les deux. Sans aucun des deux, tout attend son bilan.

Chacun choisit aussi s'il reçoit le **bilan** du matin. Sans le bilan, ce qui devait y aller ne lui parvient pas : ce
qui se passe là où il est destinataire secondaire, et le rappel de ce qui ne bouge plus. Une relance et la clôture
automatique arrivent toujours, par courriel.

Ces trois choix se font dans le menu du compte › **Profil**, section **Notifications du Support**.
Le lien **Notifications › Mes notifications**, en tête de la liste des tickets, ouvre directement cette section
du profil (`#user-options-tma`). Il n'y a plus de fenêtre séparée ni d'entrée propre au Support dans le menu du compte. Tant qu'on n'a rien
choisi, c'est le réglage de l'application qui s'applique (d'origine : les trois). L'application peut aussi fixer ce
réglage pour tous ; « Mes notifications » n'apparaît alors pas. Sans le gestionnaire d'options utilisateur, les
valeurs de l'application s'appliquent à tout le monde, sans choix personnel.

## Le bilan

Chaque jour ouvré à 8 h, chacun (sauf qui l'a refusé) reçoit **un seul courriel** :

- **ce qui l'attend** : ses demandes qui attendent sa réponse ou sa confirmation, les demandes nouvelles sans contact, ce
  qu'il traite (tickets en cours, tâches projet qui lui sont confiées), ses pauses qui reprennent aujourd'hui. Ce qui
  attend depuis plus de 5 jours, ou une tâche dont l'échéance prévue est passée, est signalé **en retard** ;
- **depuis le dernier envoi** : ce qui a été mis de côté pour lui, ticket par ticket.

Personne ne reçoit de courriel vide. L'heure, les jours et le fuseau se règlent.

**Notifications › Envoyer maintenant**, en tête de la liste des tickets, envoie tout de suite leur bilan à ceux qui
ont quelque chose en attente. Il faut le droit « Envoyer maintenant les notifications groupées ».

## Relancer

Sur la fiche d'un ticket qui attend quelqu'un d'autre, **Relancer** écrit dans l'historique un message marqué
**Relance** (votre mot, ou « Ce ticket attend votre retour. ») et prévient tout de suite qui a la main, par courriel
même s'il n'a choisi aucun canal : le demandeur quand on traite la demande, l'intervenant (ou l'équipe qui doit la
prendre) quand on l'attend. Le bouton n'apparaît pas à qui a lui-même la main, ni sur un brouillon, un ticket en
pause ou terminé. Il faut le droit « Relancer qui a la main ».

## Le rappel de ce qui ne bouge plus

Chaque jour ouvré à 7 h 30, juste avant le bilan, qui a la main reçoit dans son bilan un rappel pour :

- un ticket qui **n'a pas changé d'état depuis 7 jours** (ni brouillon, ni en pause, ni terminé) ;
- chez le Support, une **tâche en cours sans activité depuis 7 jours** (aucun message, note ou temps) : une tâche ne
  change d'état qu'à sa publication et à sa fin.

Le rappel revient tous les 7 jours tant que rien ne bouge. Le délai se règle. Un ticket qui attend le demandeur
reçoit ainsi un rappel à 7 et 14 jours, puis l'avertissement de clôture à 21 jours, et se clôt à 28. Voir
[La clôture automatique](#modal-tma.tickets.cloture-automatique).

## Ce qui prévient aussi

- **La clôture automatique** : le demandeur est averti 7 jours avant, puis à la clôture.
- **Les seuils des forfaits** : les adresses configurées. Voir [Les forfaits](#modal-tma.forfaits).

## Les tâches automatiques

| Tâche | Quand | Ce qu'elle fait |
| --- | --- | --- |
| Clôture automatique | chaque jour à 7 h | avertit puis clôture les tickets sans réponse du demandeur |
| Rappel de ce qui ne bouge plus | jours ouvrés à 7 h 30 | rappelle à qui a la main ce qui n'a pas bougé depuis 7 jours |
| Reprise des pauses | tous les quarts d'heure | reprend les tickets en pause arrivés à leur date |
| Publications programmées | chaque minute | publie les tâches et les tickets préparés arrivés à leur date |
| Bilan | jours ouvrés à 8 h | envoie le bilan |
| Ménage | chaque nuit | efface les notifications envoyées depuis plus de 90 jours |
