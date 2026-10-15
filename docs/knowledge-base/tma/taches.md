---
title: Les tâches projet
icon: heroicon-o-clipboard-document-list
order: 60
---

# Les tâches projet

Une tâche projet est un travail planifié (une évolution, un chantier), distinct d'une demande de maintenance. Elle
est créée **par le Support**, dans un [projet](#modal-tma.projets), pour l'**application cliente** de ce projet.

## Le cycle d'une tâche

| État | Ce qu'il veut dire |
| --- | --- |
| Brouillon | en attente de sa publication programmée, **invisible** de l'application cliente |
| En cours | publiée : l'application cliente la voit et peut y participer |
| Terminée | achevée ; elle peut être facturée entièrement |
| Annulée | abandonnée, avec son historique |

**Publier** fait passer la tâche de Brouillon à En cours et l'envoie à l'application cliente. On peut aussi
programmer la publication (**Publication programmée**) : elle partira toute seule à cette date.

## Chez le Support

**Support › Tâches projet › Nouveau** : le projet (qui donne l'application), le titre, début et échéance prévue
(tous deux facultatifs), date de publication éventuelle, description et pièces jointes. Sans date de publication, la
tâche part chez le client dès l'enregistrement ; avec une date, à l'heure dite (voir
[Publier une tâche](#modal-tma.taches.publication)). Puis **Terminer** ou **Annuler**, et le temps passé dans le volet
de droite (voir [Le temps passé](#modal-tma.temps)).

En tête de l'historique, la tâche se lit comme un premier message : son auteur, l'heure de sa publication, sa
description.

Le Support **modifie** une tâche (titre, description, dates, projet) à tout moment, même terminée : **⋮ ›
Modifier**. L'échéance, facultative, n'apparaît (à la création comme à la modification) qu'avec **Tout afficher** ;
cachée, elle ne change pas. Une tâche publiée ne change plus d'application : son projet se choisit parmi ceux de la
même application.

## La liste

Une ligne par tâche, rangée par projet : à gauche le numéro, le titre et le début de la description ; au milieu
l'état et la date qui compte (publication prévue, échéance prévue, **en retard** en rouge une fois l'échéance
dépassée, jour de la fin ou de l'annulation), et chez le Support l'application ; à droite le temps passé et la
dernière activité. Sur un téléphone, les blocs s'empilent. **Trier par** classe par numéro, titre, échéance ou
dernière activité.

## Supprimer une tâche

**Chez le Support seulement**, avec le droit de supprimer, quel que soit l'état de la tâche : l'icône **corbeille**
de sa ligne, **⋮ › Supprimer** sur sa fiche, ou plusieurs à la fois (cochez-les, puis **Actions groupées ›
Supprimer**). Une fenêtre prévient de tout ce qui part avec elle :

- ses **messages**, ses **notes** et son **temps saisi** ;
- si elle est publiée, sa copie **chez le client**, qui disparaît aussi ;
- les tickets ouverts depuis elle restent, sans lien.

La suppression ne se défait pas. Une tâche dont du temps est déjà rattaché à une facture ne se supprime pas. Son
numéro n'est jamais redonné, et le journal d'audit garde la trace de la suppression.

## Dans l'application cliente

Les tâches publiées pour l'application apparaissent dans **Support › Tâches projet**, rangées par projet. On y
participe par l'[historique](#modal-tma.chronologie) : messages (dans **Nouveau message**, sous l'historique), notes,
images, réponses. Une tâche **terminée** se commente encore ; une tâche **annulée** n'accepte plus que des notes. On
ne crée pas de tâche et on ne change pas son état.

## Le volet de la tâche

À droite, l'onglet **Tâche** :

- **Suivi** : son état et son **projet** (un clic ouvre le projet) ;
- **Calendrier** : début, échéance prévue, fin, publication ;
- **Tickets** : ceux ouverts à partir d'elle ;
- **Temps passé** : celui de la tâche et de tout le projet. Chez le Support, il vient **en tête**, avec le détail des
  saisies et **Saisir du temps** ; dans l'application cliente, il ferme le volet, en lecture.

Le calendrier (et, chez le Support, l'application) ne s'affiche qu'**à la demande** : l'icône en forme d'œil, en tête de
la fiche (**Tout afficher** au survol), le montre ; l'œil barré (**Masquer le détail**) le cache. Le choix vous suit
d'une page à l'autre. L'onglet **Notes** les montre toutes.

## Un ticket à partir d'une tâche

**Créer un ticket**, en tête de la fiche, ouvre un ticket de TMA qui reprend le titre, la description et
l'application de la tâche ; on les ajuste avant d'enregistrer. Le ticket suit ensuite son propre cycle (voir
[Les tickets](#modal-tma.tickets)) et reste lié à la tâche : la fiche du ticket la cite, le volet de la tâche liste
ses tickets.

## En détail

- [Les projets](#modal-tma.projets)
- [Publier une tâche](#modal-tma.taches.publication)
- [Début et échéance d'une tâche](#modal-tma.taches.periode)
