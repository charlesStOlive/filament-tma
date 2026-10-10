---
title: Les tickets
icon: heroicon-o-lifebuoy
order: 30
---

# Les tickets

Un ticket est une demande de maintenance. Il appartient à **une seule application** (celle où il a été ouvert) et,
chez le Support, à **un seul client**. Son numéro dit d'où il vient : `DES-0042` a été ouvert dans Desapp.

## Ouvrir une demande

**Support › Tickets › Nouvelle demande** : un titre, une priorité, une échéance souhaitée, une description et des
pièces jointes. La demande commence en **Brouillon** : vous seul (et votre application) la voyez. Vous pouvez la
modifier autant que vous voulez.

Ensuite :

- **Confier à l'équipe EDM** la confie à l'équipe de votre application, qui la traite sans le Support ;
- **Transmettre au Support** l'envoie au Support, qui la traitera et fera autorité. Si le Support ne répond pas,
  rien ne change et un message le dit : réessayez plus tard.

« EDM » est ici le nom court du client : voir [Les mots du module](#modal-tma.comprendre.vocabulaire).

## Les états

| État | En attente de | Ce qui se passe |
| --- | --- | --- |
| Brouillon | son auteur | en préparation, invisible des autres |
| Nouveau | l'équipe qui traite | attend d'être pris en charge |
| En cours | l'équipe qui traite | la demande est traitée |
| En attente du demandeur | le demandeur | des précisions lui ont été demandées |
| En pause | l'équipe qui traite | suspendu jusqu'à une date, puis repris tout seul |
| Résolu | le demandeur | le travail est fait : il confirme, ou rouvre |
| Clos | — | terminé ; c'est l'état qui permet la facturation complète |
| Annulé | — | abandonné ou refusé, avec son motif |

L'équipe qui traite est le **Support**, ou l'**équipe du client** (« Équipe EDM ») : la fiche l'affiche dans
**Suivi › Traité par**.

## Les passages

Les boutons en haut de la fiche ne montrent que les passages possibles **maintenant**, pour **vous**.

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
| Rouvrir | le demandeur | ce qui ne va pas |
| Clore sans réponse | l'équipe qui traite | un motif (facultatif) |
| Annuler | le demandeur avant la prise en charge, l'équipe qui traite ensuite | un motif (sauf en brouillon) |

Un **commentaire** ne fait jamais changer l'état : pour rendre la main, il faut le passage qui le dit (« Répondre »,
« Confirmer et clore »…). Voir [La chronologie](#modal-tma.chronologie).

## Qui peut modifier un ticket

- En **brouillon** : son auteur.
- Ensuite : l'**équipe qui traite** la demande, quand le ticket l'attend.
- Une fois un ticket transmis au Support, l'application cliente ne modifie plus ses champs : elle ajoute des
  commentaires, des notes, des fichiers.

## La clôture automatique

Quand le ticket attend le demandeur (des précisions, ou sa confirmation) et que **rien ne se passe pendant 28
jours**, il est clos automatiquement. Le demandeur reçoit un courriel **7 jours avant**, puis à la clôture. Un
simple commentaire remet le compte à zéro. Voir [Rappels et notifications](#modal-tma.notifications).

## Les tickets préparés par le Support

Le Support peut ouvrir un ticket **pour** un client, au nom de l'un de ses utilisateurs : il choisit l'application,
puis le demandeur parmi les utilisateurs qui ont accès au Support. Le ticket reste **invisible** chez le client tant
qu'il est en brouillon ; **Publier chez EDM** l'y fait apparaître, tout de suite ou à la date de **publication
programmée**.

## En détail

- [Priorité et échéance](#modal-tma.tickets.priorite-echeance)
- [Confier à l'équipe ou transmettre au Support](#modal-tma.tickets.soumettre)
- [Demander des précisions, répondre](#modal-tma.tickets.question-reponse)
- [La pause et sa reprise](#modal-tma.tickets.pause)
- [Résolu : confirmer ou rouvrir](#modal-tma.tickets.validation)
- [Annuler, refuser, clore sans réponse](#modal-tma.tickets.annuler)
- [La clôture automatique](#modal-tma.tickets.cloture-automatique)
- [Les tickets préparés par le Support](#modal-tma.tickets.prepares)
