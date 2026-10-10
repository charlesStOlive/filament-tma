---
title: Comprendre la TMA
icon: heroicon-o-light-bulb
order: 10
---

# Comprendre la TMA

Le module TMA suit les **demandes de maintenance** (les tickets) et les **tâches projet**, avec le temps passé, les
forfaits et ce qui reste à facturer. Il relie deux sortes d'applications.

## Le centre de service et les applications clientes

- **Le centre de service** est l'application de l'équipe qui fait la maintenance (Filbreeze). C'est le **maître** :
  il reçoit les demandes de tous les clients, y saisit le temps, crée les tâches projet, suit les forfaits.
- **Une application cliente** est l'application d'un client (Desapp…). Ses utilisateurs y ouvrent leurs demandes et
  suivent ce qui les concerne.

Une application cliente ne parle qu'au centre de service, jamais à une autre application cliente. Le centre de
service ne voit d'un client que ce que ce client lui a confié.

## Deux façons de traiter une demande

| Traitement | Qui s'en occupe | Où |
| --- | --- | --- |
| **Local** | un collègue de la même application | la demande ne quitte pas l'application cliente |
| **Centre de service** | l'équipe du centre de service | la demande est transmise au maître, qui fait autorité |

Une demande commence toujours en local. On la **transmet au centre de service** quand on le décide ; l'inverse
n'existe pas. Voir [Les tickets](#modal-tma.tickets).

## Demandeur, intervenant, et « qui a la main »

Chaque ticket a deux parties :

- le **demandeur**, du côté de l'application qui a ouvert la demande ;
- l'**intervenant**, qui la traite : un collègue (traitement local) ou le centre de service.

À chaque étape, **la main** est à l'une des deux. Celle qui a la main fait avancer le ticket ; l'autre ne peut plus
qu'**ajouter** : un commentaire, une note, une pièce jointe. Elle ne modifie plus le ticket et ne fait pas les
passages réservés à l'autre partie.

Exemple : le centre de service pose une question (« Demander une réponse ») ; la main passe au demandeur. Un
commentaire de l'intervenant pendant ce temps ne la reprend pas ; la réponse du demandeur (« Répondre ») la lui
rend.

## Ce que l'on trouve dans le module

- [Les tickets](#modal-tma.tickets) et leur [chronologie](#modal-tma.chronologie) ;
- [le temps passé](#modal-tma.temps) ;
- [les tâches projet](#modal-tma.taches) ;
- [les forfaits](#modal-tma.forfaits) ;
- [les rappels et notifications](#modal-tma.notifications) ;
- [la synchronisation](#modal-tma.synchronisation) entre les applications ;
- [les droits](#modal-tma.droits) ;
- [les commandes](#modal-tma.commandes) pour un assistant IA ;
- [l'audit et le nettoyage](#modal-tma.audit-nettoyage).

Pour installer et relier les applications : [Configuration de départ](#modal-tma.demarrer).

## En détail

- [Qui a la main ?](#modal-tma.comprendre.la-main)
- [Les agents du centre de service](#modal-tma.agents)
- [Traitement local ou centre de service](#modal-tma.comprendre.traitement)
