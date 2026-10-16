---
title: L'historique
icon: heroicon-o-chat-bubble-left-right
order: 40
---

# L'historique

La fiche d'un ticket ou d'une tâche commence par l'**historique** : la **demande** (ou la description de la tâche),
puis tout ce qui s'est passé, dans l'ordre : messages, réponses, changements d'état, avec leur auteur et leur date.
Dessous, à part, le **nouveau message**. Le suivi (état, qui doit agir…) et les notes sont dans le volet de droite.

## Un changement d'état et son message

Un changement d'état fait **avec** un message s'affiche dans le bloc de ce message : « Ada Lovelace — Demander des
précisions [En cours] → [En attente du demandeur] », puis la question. De même :

- la **demande** porte son premier passage : « — Transmettre au Support [Brouillon] → [Nouveau] » (ou Publier) ; la
  description d'une tâche, sa publication ;
- la **réponse**, « Résolu » et son texte, « Confirmer et clore » avec un mot, « Terminer » une tâche avec son mot de la
  fin : le passage est dans le bloc du message ;
- un passage qui a un **motif** (retirer, refuser, forcer la clôture, mettre en pause) est un bloc à lui, le motif
  pour texte.

Un passage fait seul (une pause sans motif, une reprise) reste une ligne ; une relance écrite ensuite, même une minute
après, reste un bloc à elle.

## Message, réponse, note

| Entrée | Ce que c'est | Qui la lit |
| --- | --- | --- |
| **Message** | un tour de parole : seule la partie dont c'est le tour en écrit un | tout le monde sur le ticket, des deux côtés |
| **Réponse** | le message par lequel on répond quand on est attendu ; elle s'affiche sous la question | comme le message |
| **Note** | un commentaire à côté, sur un message ou sur le ticket entier | tout le monde sur le ticket, des deux côtés |

Sur une tâche projet, ce qui a été fait, écrit avec une saisie de temps, est un message comme les autres ; sur un
ticket, le temps n'a pas de texte.

Sur un ticket, un message va toujours avec un passage (une question, une réponse, « résolu »…) : il n'y a pas de
message à part. Pour un point d'étape ou une précision, on écrit une note ; on peut aussi relancer. Un brouillon ne reçoit pas de message : sa demande est le premier.
Sur un ticket terminé, on n'écrit plus de message ; les notes restent possibles. Sur une tâche, on écrit dès sa publication (pas avant) et encore une fois terminée ; plus une fois
annulée. Voir [Les notes](#modal-tma.chronologie.notes).

## Modifier son message

**Modifier**, sous un message, le rouvre en fenêtre. Seul son **auteur** le voit, et seulement **tant que c'est le
dernier message** du ticket, que ce soit son tour ou non : dès qu'un autre message le suit, plus personne ne touche aux
messages précédents. La règle est la même pour tous. Un message modifié porte la mention « modifié » (survolée, elle
donne l'heure). Les notes ne comptent pas : elles n'empêchent pas de modifier le dernier message.

La **demande** compte comme le premier message : son auteur la modifie (**Modifier**, en haut de la fiche) tant
qu'aucun message ne l'a suivie.

## Le nouveau message

Sous l'historique : un texte, des pièces jointes, et des boutons qui dépendent de **qui doit agir** (la zone le
rappelle au-dessus du texte).

| Vous êtes… | Les boutons |
| --- | --- |
| le demandeur, et on attend votre réponse | **Répondre** |
| le demandeur, et le ticket est résolu | **Confirmer et clore** (avec un mot, si vous voulez), ou **Retour au Support** (en disant ce qui ne va pas) |
| le Support, et c'est son tour | **Demander des précisions** ou **Marquer comme résolu** (passent la main au demandeur) ; pour un point d'étape, une note |
| quelqu'un dont ce n'est pas le tour | rien à envoyer : la zone le dit, et renvoie aux notes |

Sur une tâche projet, **Envoyer le message** écrit un message, pour qui en a le droit.

Seul le demandeur répond ; un collègue écrit une note, ou **prend la main** s'il est destinataire principal (voir
[Prendre la main](#modal-tma.tickets.question-reponse)). Les notes s'écrivent à part, sous le message qu'elles
commentent.

## Les fichiers

On joint des fichiers à un ticket, à une tâche ou à un message : images, PDF, documents de bureautique, CSV, ZIP,
jusqu'à 10 Mo chacun (réglable). Le type est vérifié sur le contenu réel du fichier ; un script ou un exécutable
est refusé.

Les fichiers sont rangés à part, jamais accessibles directement : chaque téléchargement vérifie que vous pouvez voir
le ticket. Une image s'affiche en vignette ; un clic l'ouvre.

## Le texte mis en forme

Les descriptions et les messages acceptent le gras, l'italique, les listes, les citations, le code et les liens.
Tout est nettoyé à l'enregistrement : ni script, ni contenu actif, ni lien autre que web ou courriel.

## Sur un ticket transmis

Dans l'application cliente, une réponse, une relance ou une note part au Support **au moment où on l'écrit** ; de
même quand on modifie son message, archive ou supprime une note. S'il ne répond pas, le message n'est pas enregistré et un avertissement le dit : rien n'est perdu en
silence. Ce que le Support écrit arrive dans l'application cliente quelques instants plus tard. Voir
[La synchronisation](#modal-tma.synchronisation).

## En détail

- [Les notes](#modal-tma.chronologie.notes)
- [Les pièces jointes](#modal-tma.chronologie.fichiers)
- [La mise en forme du texte](#modal-tma.chronologie.mise-en-forme)
