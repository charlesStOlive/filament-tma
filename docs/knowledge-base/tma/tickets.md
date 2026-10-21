---
title: Les tickets
icon: heroicon-o-lifebuoy
order: 30
---

# Les tickets

Un ticket est une demande de maintenance, d'une application cliente au Support. Il appartient à **une seule
application** (celle où il a été ouvert) et, chez le Support, à **un seul client**. Son numéro dit d'où il vient :
`DES-0042` a été ouvert dans Desapp.

## Ouvrir une demande

**Support › Tickets › Nouvelle demande** : un titre, une priorité, une description et des pièces jointes. Il faut être
**correspondant du Support**. La demande commence en **Brouillon** : elle reste dans votre application, et vous la
modifiez autant que vous voulez.

Depuis une [tâche projet](#modal-tma.taches), **Créer un ticket** ouvre la même page, préremplie avec la tâche ; le
ticket reste lié à elle.

Ensuite, **Transmettre au Support** : la demande part au Support, qui la traitera et fera autorité. Si le Support ne
répond pas, rien ne change et un message le dit : réessayez plus tard. Voir
[Transmettre au Support](#modal-tma.tickets.soumettre).

## Les états

| État | En attente de | Ce qui se passe |
| --- | --- | --- |
| Brouillon | son auteur | en préparation, invisible des autres |
| Nouveau | le Support | transmis : attend la première réponse du Support |
| En cours | le Support | la demande est traitée |
| En attente du demandeur | le demandeur | des précisions lui ont été demandées |
| En pause | le Support | suspendu jusqu'à une date, puis repris tout seul |
| Résolu | le demandeur | le travail est fait : il confirme, ou renvoie la demande |
| Clos | — | terminé ; c'est l'état qui permet la facturation complète |
| Annulé | — | retiré par son demandeur ou refusé par le Support, avec son motif |

**Seule la partie dont c'est le tour agit**, et du côté du client, seulement le demandeur lui-même : aucun droit ne
permet d'agir à la place de l'autre. Voir [Qui doit agir ?](#modal-tma.comprendre.la-main).

## Les passages

Les boutons ne montrent que les passages possibles **maintenant**, pour **vous** : en haut de la fiche, et dans la
**zone Nouveau message**, sous l'historique, pour ceux qui portent un texte (Répondre, Demander des précisions, Marquer
comme résolu, Confirmer et clore, Retour au Support). Voir [L'historique](#modal-tma.chronologie).

| Passage | Par | Depuis | Ce qu'il demande |
| --- | --- | --- | --- |
| Transmettre au Support | le demandeur | Brouillon | le contact au Support (d'office s'il n'y en a qu'un) |
| Retirer la demande | le demandeur | Brouillon, Nouveau | un motif (sauf en brouillon) |
| Refuser | le Support | Nouveau | un motif |
| Demander des précisions | le Support | Nouveau, En cours | la question |
| Marquer comme résolu | le Support | Nouveau, En cours | ce qui a été fait (facultatif) |
| Mettre en pause | le Support | Nouveau, En cours | la date de reprise |
| Reprendre | le Support | En pause | — |
| Répondre | le demandeur | En attente du demandeur | la réponse |
| Confirmer et clore | le demandeur | Résolu | un mot (facultatif) |
| Retour au Support | le demandeur | Résolu | ce qui ne va pas |
| Prendre la main | un autre destinataire principal, dans l'application du demandeur | En attente du demandeur, Résolu | une confirmation |

À part, les **forçages**, chacun avec son droit : **Forcer la clôture** et **Forcer le retour**. Voir
[Les forçages](#modal-tma.tickets.forcages).

Il n'y a pas de « prise en charge » : le ticket arrive chez le contact au Support choisi à la transmission, qui répond
tout de suite. Un **message** va toujours avec un passage ; pour le reste, une note. Voir
[L'historique](#modal-tma.chronologie).

## La liste

Au-dessus de la liste, des **raccourcis** remplissent ses filtres d'un clic ; on peut ensuite les affiner, et le
raccourci s'éteint dès que les filtres ne sont plus les siens :

| Raccourci | Filtres |
| --- | --- |
| En attente de moi | vos brouillons, vos demandes qui attendent votre réponse ou votre confirmation ; côté Support, ce dont vous êtes le contact, et les demandes nouvelles sans contact |
| Mes demandes | vos demandes, sauf celles en pause, closes ou annulées (application cliente) |
| Actifs | tous les tickets, sauf ceux en pause, clos ou annulés |
| Tous | aucun filtre |

On arrive sur **Mes demandes** dans une application cliente, sur **Actifs** chez le Support et pour ses membres. La colonne **En attente de** dit qui doit agir : **Vous**, le demandeur par son nom, ou le
**Support**.

## Qui peut modifier un ticket

- **La demande** (titre, description, priorité, pièces jointes) : son auteur, en brouillon puis **tant qu'aucun message
  ne l'a suivie**, même au tour du Support. Après la transmission, son application l'envoie au Support : s'il refuse ou
  ne répond pas, rien n'est gardé.
- **Le titre et la priorité** : le Support aussi, à son tour (jamais la description).
- Ensuite, on ajoute : des notes, des fichiers ; et on modifie son dernier message, tant qu'il est le dernier.

## Sur le tableau de bord

Le widget **Mes tickets en cours** reprend les tickets en cours (ceux de **Actifs**) dont vous êtes destinataire,
principal ou secondaire : ceux qui vous attendent d'abord, puis les plus récemment actifs, dix au plus. Pour chacun :
votre place parmi les destinataires, son état, qui il attend (**Vous**, en orange, quand c'est vous), et **Ouvrir**.
**Tous les tickets** mène à la liste. Il s'affiche à qui voit les tickets.

## La clôture automatique

Quand le ticket attend le demandeur (des précisions, ou sa confirmation) et que **rien ne se passe pendant 28
jours**, il est clos automatiquement. Le demandeur est averti **7 jours avant** (un courriel s'il a choisi l'e-mail
direct, sinon son bilan du matin), puis la clôture arrive dans sa cloche et son bilan. Sa réponse, ou une simple note,
remet le compte à zéro. Voir [Rappels et notifications](#modal-tma.notifications).

## Les tickets préparés par le Support

Le Support peut ouvrir un ticket **pour** un client, au nom de l'un de ses correspondants : il choisit l'application,
puis le demandeur parmi ses correspondants du Support. Le ticket reste **invisible** chez le client tant
qu'il est en brouillon ; **Publier chez EDM** l'y fait apparaître, tout de suite ou à la date de **publication
programmée**.

## En détail

- [La priorité](#modal-tma.tickets.priorite-echeance)
- [Transmettre au Support](#modal-tma.tickets.soumettre)
- [Demander des précisions, répondre, prendre la main](#modal-tma.tickets.question-reponse)
- [La pause et sa reprise](#modal-tma.tickets.pause)
- [Résolu : confirmer ou renvoyer](#modal-tma.tickets.validation)
- [Retirer, refuser](#modal-tma.tickets.annuler)
- [Les forçages](#modal-tma.tickets.forcages)
- [La clôture automatique](#modal-tma.tickets.cloture-automatique)
- [Les tickets préparés par le Support](#modal-tma.tickets.prepares)
