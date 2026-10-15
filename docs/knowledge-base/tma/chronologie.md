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

- la **demande** porte son premier passage : « — Transmettre au Support [Brouillon] → [Nouveau] » (ou Confier à
  l'équipe, Publier) ; la description d'une tâche, sa publication ;
- la **réponse**, « Résolu » et son texte, « Confirmer et clore » avec un mot, « Terminer » une tâche avec son mot de la
  fin : le passage est dans le bloc du message ;
- un passage qui a un **motif** (annuler, clore, mettre en pause) est un bloc à lui, le motif pour texte.

Un passage fait seul (« Prendre en charge ») reste une ligne ; un message écrit ensuite, même une minute après, reste
un bloc à lui.

## Message, réponse, note

| Entrée | Ce que c'est | Qui la lit |
| --- | --- | --- |
| **Message** | l'échange entre les deux parties | tout le monde sur le ticket, des deux côtés |
| **Réponse** | le message par lequel on répond quand on est attendu ; elle s'affiche sous la question | comme le message |
| **Note** | un commentaire à côté, sur un message ou sur le ticket entier | tout le monde sur le ticket, des deux côtés |

Ce qui a été fait, écrit avec une saisie de temps (par la commande `tma:log`), est un message comme les autres.

Ajouter une entrée ne change jamais l'état du ticket. Sur un ticket terminé, on n'écrit plus de message ; les notes
restent possibles. Sur une tâche, on écrit dès sa publication (pas avant) et encore une fois terminée ; plus une fois
annulée. Voir [Les notes](#modal-tma.chronologie.notes).

## Modifier son message

**Modifier**, sous un message, le rouvre en fenêtre. Seul son **auteur** le voit, et seulement **tant que personne
d'autre n'a écrit de message après lui**, même s'il a perdu la main entre-temps. Un message modifié porte la mention
« modifié » (survolée, elle donne l'heure). Les notes posées sur un message ne l'empêchent pas d'être modifié.

## Le nouveau message

Sous l'historique : un texte, des pièces jointes, et des boutons qui dépendent de **qui doit agir** (la zone le
rappelle au-dessus du texte).

| Vous êtes… | Les boutons |
| --- | --- |
| le demandeur, et on attend votre réponse | **Répondre** |
| le demandeur, et le ticket est résolu | **Confirmer et clore**, ou **Retour au Support** (en disant ce qui ne va pas) |
| l'équipe qui traite, et le ticket l'attend | **Envoyer le message** (garde la main), **Demander des précisions** ou **Marquer comme résolu** (passent la main au demandeur) |
| l'autre partie | **Envoyer le message** : une précision, qui ne change pas l'état |

Sur un ticket nouveau, « Demander des précisions » et « Marquer comme résolu » le prennent en charge au passage.

Seul le demandeur répond pour le demandeur ; un collègue ne peut qu'écrire un message. Les notes s'écrivent à part,
sous le message qu'elles commentent.

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

Dans l'application cliente, un message, une réponse ou une note part au Support **au moment où on l'écrit** ; de
même quand on modifie son message, archive ou supprime une note. S'il ne répond pas, le message n'est pas enregistré et un avertissement le dit : rien n'est perdu en
silence. Ce que le Support écrit arrive dans l'application cliente quelques instants plus tard. Voir
[La synchronisation](#modal-tma.synchronisation).

## En détail

- [Les notes](#modal-tma.chronologie.notes)
- [Les pièces jointes](#modal-tma.chronologie.fichiers)
- [La mise en forme du texte](#modal-tma.chronologie.mise-en-forme)
