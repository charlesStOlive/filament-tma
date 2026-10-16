---
title: Comprendre le module
icon: heroicon-o-light-bulb
order: 10
---

# Comprendre le module

Le module suit les **demandes** (les tickets) et les **tâches projet**, avec le temps passé, les forfaits et ce qui
reste à facturer. Il relie deux sortes d'applications.

## Le Support et les applications clientes

- **Le Support** est l'équipe qui fait la maintenance, et son application (Filbreeze). C'est le **maître** : il
  reçoit les demandes de tous les clients, y saisit le temps, crée les tâches projet, suit les forfaits.
- **Une application cliente** est l'application d'un client (Desapp…). Ses utilisateurs y ouvrent leurs demandes et
  suivent ce qui les concerne.

Une application cliente ne parle qu'au Support, jamais à une autre application cliente. Le Support ne voit d'un
client que ce que ce client lui a transmis.

## Qui parle au Support

Un ticket va **toujours** d'une application cliente au Support. Une seule chose compte côté client : le droit
**Correspondant du Support** (voir [Les droits](#modal-tma.droits)).

| Qui | Ce qu'il fait |
| --- | --- |
| **Les correspondants du Support** : les personnes du client qui ont ce droit | écrivent une demande, la **transmettent au Support**, en sont le demandeur ; le Support peut les nommer (demandeur d'un ticket préparé, destinataire) |
| **Le Support** : ses membres (le droit **Membre du Support**) | reçoit les demandes, les traite, et **fait autorité** dès qu'un ticket est transmis |

Les autres utilisateurs du client n'ont rien à faire dans le module.

## Le tour

Chaque ticket a deux parties : le **demandeur** (une personne du client) et le **Support**. À chaque étape, le ticket
attend **l'une des deux** : la fiche l'affiche à droite, dans **Suivi › En attente de**.

**Seule la partie dont c'est le tour agit**, et du côté du client, **seulement le demandeur lui-même**. Aucun droit ne
permet d'agir à la place de l'autre, pas même celui d'un administrateur. L'autre partie peut écrire une **note**, ou
modifier son dernier message s'il est encore le dernier.

Un message va toujours avec un passage : le Support pose une question (« Demander des précisions ») ou marque le
ticket comme résolu, le demandeur répond, confirme ou renvoie. Pour le reste (un point d'étape, une précision), on
écrit une note.

Exemple : le Support pose une question ; le ticket attend le demandeur. Le Support ne peut plus que lui écrire une note
ou le relancer ; la réponse du demandeur (« Répondre ») lui rend la main.

En transmettant, le demandeur choisit son **contact au Support** (d'office s'il n'y en a qu'un) : le ticket arrive
chez lui, qui répond tout de suite. Il n'y a pas de « prise en charge ».

Deux exceptions, voulues :

- le demandeur peut **retirer sa demande** tant qu'elle est **Nouveau** (le Support n'a pas encore répondu) ;
- un **forçage** (« Forcer la clôture », « Forcer le retour ») passe outre le tour, avec son propre droit, réservé au
  Support (voir [Les forçages](#modal-tma.tickets.forcages)).

Si le demandeur est absent, un autre **destinataire principal** du ticket, dans son application, peut **prendre la
main** : il devient le demandeur (voir [Prendre la main](#modal-tma.tickets.question-reponse)).

## Ce que l'on trouve dans le module

- [Les tickets](#modal-tma.tickets) et leur [historique](#modal-tma.chronologie) ;
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

- [Les mots du module](#modal-tma.comprendre.vocabulaire)
- [Qui doit agir ?](#modal-tma.comprendre.la-main)
- [Du client au Support](#modal-tma.comprendre.traitement)
- [Les membres du Support dans une application cliente](#modal-tma.agents)
