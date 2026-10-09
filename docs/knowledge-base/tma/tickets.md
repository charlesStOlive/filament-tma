---
title: Les tickets
icon: heroicon-o-lifebuoy
order: 30
---

# Les tickets

Un ticket est une demande de maintenance. Il appartient à **une seule application** (celle où il a été ouvert) et,
dans le centre de service, à **un seul client**. Son numéro dit d'où il vient : `DES-0042` a été ouvert dans
Desapp.

## Ouvrir une demande

**TMA › Tickets › Nouvelle demande** : un titre, une priorité, une échéance souhaitée, une description et des pièces
jointes. La demande commence en **Brouillon** : vous seul (et votre application) la voyez. Vous pouvez la modifier
autant que vous voulez.

Ensuite :

- **Soumettre** la confie à un collègue de l'application (traitement local) ;
- **Transmettre au centre de service** l'envoie au maître, qui la traitera et fera autorité. Si le centre de service
  ne répond pas, rien ne change et un message le dit : réessayez plus tard.

## Les états

| État | La main est à | Ce qui se passe |
| --- | --- | --- |
| Brouillon | son auteur | en préparation, invisible des autres |
| Soumis | l'intervenant | attend d'être pris en charge |
| En cours | l'intervenant | il traite la demande |
| En attente du demandeur | le demandeur | une question lui a été posée |
| En pause | l'intervenant | suspendu jusqu'à une date, puis repris tout seul |
| À valider | le demandeur | le travail est fait : à valider, ou à rouvrir |
| Clôturé | — | terminé ; c'est l'état qui permet la facturation complète |
| Annulé | — | abandonné ou refusé, avec son motif |

## Les passages

Les boutons en haut de la fiche ne montrent que les passages possibles **maintenant**, pour **vous**.

| Passage | Par | Ce qu'il demande |
| --- | --- | --- |
| Soumettre | le demandeur | un intervenant (facultatif) |
| Transmettre au centre de service | le demandeur (brouillon) ou l'intervenant local | une confirmation |
| Prendre en charge | l'intervenant | — ; vous devenez l'intervenant |
| Refuser | l'intervenant | un motif |
| Demander une réponse | l'intervenant | la question |
| Répondre | le demandeur | la réponse |
| Mettre en pause | l'intervenant | la date de reprise |
| Reprendre | l'intervenant | — |
| Soumettre à validation | l'intervenant | ce qui a été fait (facultatif) |
| Valider | le demandeur | une confirmation |
| Rouvrir | le demandeur | ce qui ne va pas |
| Clôturer sans réponse | l'intervenant | un motif (facultatif) |
| Annuler | le demandeur avant la prise en charge, l'intervenant ensuite | un motif (sauf en brouillon) |

Un **commentaire** ne fait jamais changer l'état : pour rendre la main, il faut le passage qui le dit (« Répondre »,
« Valider »…). Voir [La chronologie](#modal-tma.chronologie).

## Qui peut modifier un ticket

- En **brouillon** : son auteur.
- Ensuite : l'**intervenant**, quand la main est à lui.
- Une fois un ticket transmis, l'application cliente ne modifie plus ses champs : elle ajoute des commentaires,
  des notes, des fichiers.

## La clôture automatique

Quand la main est au demandeur (en attente de sa réponse, ou à valider) et que **rien ne se passe pendant 28 jours**,
le ticket est clôturé automatiquement. Le demandeur reçoit un courriel **7 jours avant**, puis à la clôture. Un simple
commentaire remet le compte à zéro. Voir [Rappels et notifications](#modal-tma.notifications).

## Les tickets préparés par le centre de service

Le centre de service peut ouvrir un ticket **pour** un client, au nom de l'un de ses utilisateurs : il choisit
l'application, puis le demandeur parmi les utilisateurs qui ont accès au centre de service. Le ticket reste
**invisible** chez le client tant qu'il est en brouillon ; **Publier chez le client** l'y fait apparaître, tout de
suite ou à la date de **publication programmée**.

## En détail

- [Priorité et échéance](#modal-tma.tickets.priorite-echeance)
- [Soumettre ou transmettre](#modal-tma.tickets.soumettre)
- [Demander une réponse, répondre](#modal-tma.tickets.question-reponse)
- [La pause et sa reprise](#modal-tma.tickets.pause)
- [Soumettre à validation, valider, rouvrir](#modal-tma.tickets.validation)
- [Annuler, refuser, clôturer sans réponse](#modal-tma.tickets.annuler)
- [La clôture automatique](#modal-tma.tickets.cloture-automatique)
- [Les tickets préparés par le centre de service](#modal-tma.tickets.prepares)
