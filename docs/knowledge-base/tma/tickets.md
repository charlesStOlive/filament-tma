---
title: Les tickets
icon: heroicon-o-lifebuoy
order: 30
---

# Les tickets

Un ticket est une demande de maintenance. Il appartient à **une seule application** (celle où il a été ouvert) et,
chez le Support, à **un seul client**. Son numéro dit d'où il vient : `DES-0042` a été ouvert dans Desapp.

## Ouvrir une demande

**Support › Tickets › Nouvelle demande** : un titre, une priorité, une description et des pièces jointes. La
demande commence en **Brouillon** : vous seul (et votre application) la voyez. Vous pouvez la modifier autant que
vous voulez.

Depuis une [tâche projet](#modal-tma.taches), **Créer un ticket** ouvre la même page, préremplie avec la tâche ; le
ticket reste lié à elle.

Ensuite, selon votre rôle :

- **Confier à l'équipe EDM** : vous n'êtes pas en lien avec le Support ; l'équipe de votre application traite la
  demande, ou la transmet au Support ;
- **Transmettre au Support** : vous faites partie de l'équipe EDM ; la demande part au Support, qui la traitera et
  fera autorité. Si le Support ne répond pas, rien ne change et un message le dit : réessayez plus tard.

« EDM » est ici le nom court du client : voir [Les mots du module](#modal-tma.comprendre.vocabulaire).

## Les états

| État | En attente de | Ce qui se passe |
| --- | --- | --- |
| Brouillon | son auteur | en préparation, invisible des autres |
| Nouveau | l'équipe qui traite | attend d'être pris en charge |
| En cours | l'équipe qui traite | la demande est traitée |
| En attente du demandeur | le demandeur | des précisions lui ont été demandées |
| En pause | l'équipe qui traite | suspendu jusqu'à une date, puis repris tout seul |
| Résolu | le demandeur | le travail est fait : il confirme, ou renvoie la demande |
| Clos | — | terminé ; c'est l'état qui permet la facturation complète |
| Annulé | — | abandonné ou refusé, avec son motif |

L'équipe qui traite est le **Support**, ou l'**équipe du client** (« Équipe EDM ») : la fiche l'affiche dans
**Suivi › Traité par**.

## Les passages

Les boutons ne montrent que les passages possibles **maintenant**, pour **vous** : en haut de la fiche, et dans la
**zone Nouveau message**, sous l'historique, pour ceux qui portent un texte (Répondre, Demander des précisions, Marquer
comme résolu, Confirmer et clore, Retour au Support). Voir [L'historique](#modal-tma.chronologie).

| Passage | Par | Ce qu'il demande |
| --- | --- | --- |
| Confier à l'équipe EDM | le demandeur | la personne à qui la confier (facultatif) |
| Transmettre au Support | le demandeur (brouillon) ou l'équipe du client | une confirmation |
| Prendre en charge | l'équipe qui traite | — ; vous apparaissez dans « Pris en charge par » |
| Refuser | l'équipe qui traite | un motif |
| Demander des précisions | l'équipe qui traite | la question |
| Répondre | le demandeur | la réponse |
| Mettre en pause | l'équipe qui traite | la date de reprise |
| Reprendre | l'équipe qui traite | — |
| Marquer comme résolu | l'équipe qui traite | ce qui a été fait (facultatif) |
| Confirmer et clore | le demandeur | une confirmation |
| Retour au Support (ou à l'équipe EDM) | le demandeur | ce qui ne va pas |
| Clore sans réponse | l'équipe qui traite | un motif (facultatif) |
| Annuler | le demandeur avant la prise en charge, l'équipe qui traite ensuite | un motif (sauf en brouillon) |

Un **message** ne fait jamais changer l'état : pour rendre la main, il faut le passage qui le dit (« Répondre »,
« Confirmer et clore »…). Voir [L'historique](#modal-tma.chronologie).

## La liste

Au-dessus de la liste, des **raccourcis** remplissent ses filtres d'un clic ; on peut ensuite les affiner, et le
raccourci s'éteint dès que les filtres ne sont plus les siens :

| Raccourci | Filtres |
| --- | --- |
| En attente de moi | vos brouillons, vos demandes qui attendent votre réponse ou votre confirmation, ce qu'on vous a confié, et les nouvelles demandes de votre équipe que personne n'a prises (si vous pouvez les prendre) |
| Mes demandes | vos demandes, sauf celles en pause, closes ou annulées (application cliente) |
| Actifs | tous les tickets, sauf ceux en pause, clos ou annulés |
| Tous | aucun filtre |

On arrive sur **Mes demandes** dans une application cliente, sur **Actifs** chez le Support et pour ses membres. La colonne **En attente de** dit qui doit agir : **Vous**, le demandeur par son nom, le
**Support** ou l'**Équipe EDM**.

## Qui peut modifier un ticket

- En **brouillon** : son auteur.
- Ensuite : l'**équipe qui traite** la demande, quand le ticket l'attend.
- Une fois un ticket transmis au Support, l'application cliente ne modifie plus ses champs : elle ajoute des
  messages, des notes, des fichiers.

## La clôture automatique

Quand le ticket attend le demandeur (des précisions, ou sa confirmation) et que **rien ne se passe pendant 28
jours**, il est clos automatiquement. Le demandeur reçoit un courriel **7 jours avant**, puis à la clôture. Un
simple message remet le compte à zéro. Voir [Rappels et notifications](#modal-tma.notifications).

## Les tickets préparés par le Support

Le Support peut ouvrir un ticket **pour** un client, au nom de l'un de ses utilisateurs : il choisit l'application,
puis le demandeur parmi les utilisateurs qui ont accès au Support. Le ticket reste **invisible** chez le client tant
qu'il est en brouillon ; **Publier chez EDM** l'y fait apparaître, tout de suite ou à la date de **publication
programmée**.

## En détail

- [La priorité](#modal-tma.tickets.priorite-echeance)
- [Confier à l'équipe ou transmettre au Support](#modal-tma.tickets.soumettre)
- [Demander des précisions, répondre](#modal-tma.tickets.question-reponse)
- [La pause et sa reprise](#modal-tma.tickets.pause)
- [Résolu : confirmer ou renvoyer](#modal-tma.tickets.validation)
- [Annuler, refuser, clore sans réponse](#modal-tma.tickets.annuler)
- [La clôture automatique](#modal-tma.tickets.cloture-automatique)
- [Les tickets préparés par le Support](#modal-tma.tickets.prepares)
