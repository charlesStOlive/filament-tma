---
title: La chronologie
icon: heroicon-o-chat-bubble-left-right
order: 40
---

# La chronologie

Sous la demande, la chronologie montre tout ce qui s'est passé sur le ticket ou la tâche, dans l'ordre : messages,
notes internes, changements d'état, avec leur auteur et leur date. Le suivi (état, qui doit agir…) est dans le
volet de droite.

## Message, note interne, réponse

| Entrée | Qui la lit |
| --- | --- |
| **Message** | tout le monde sur le ticket, demandeur compris, des deux côtés |
| **Note interne** | seulement votre équipe : l'application où elle a été écrite, et les personnes qui ont le droit des notes |
| **Réponse** | comme le message auquel elle répond |

- On écrit dans la **zone de réponse**, sous la chronologie (voir plus bas).
- Une réponse à une question s'affiche sous la question, en fil.
- **Partager avec l'autre équipe**, sous une note interne, la rend visible de l'autre équipe (l'équipe du client ou le
  Support), pas du demandeur. Ce partage ne se retire pas.

Ce qui a été fait, écrit avec une saisie de temps (par la commande `tma:log`), est un message comme les autres.

Ajouter une entrée ne change jamais l'état du ticket. Sur un ticket terminé, on n'écrit plus de message ; les notes
internes restent possibles.

Une note du Support ne quitte jamais le Support tant qu'elle n'est pas partagée ; une note d'une application
cliente non plus. Chaque application peut retirer le partage, ou les notes internes elles-mêmes (voir
[Message ou note interne ?](#modal-tma.chronologie.commentaire-ou-note)).

## La zone de réponse

Sous la chronologie : un texte, des pièces jointes, et des boutons qui dépendent de **qui doit agir** (la zone le
rappelle au-dessus du texte).

| Vous êtes… | Les boutons |
| --- | --- |
| le demandeur, et on attend votre réponse | **Répondre** |
| le demandeur, et le ticket est résolu | **Confirmer et clore**, ou **Rouvrir** (en disant ce qui ne va pas) |
| l'équipe qui traite, et le ticket l'attend | **Envoyer le message** (garde la main), **Demander des précisions** ou **Marquer comme résolu** (passent la main au demandeur) |
| l'autre partie | **Envoyer le message** : une précision, qui ne change pas l'état |

Sur un ticket nouveau, « Demander des précisions » et « Marquer comme résolu » le prennent en charge au passage.

Seul le demandeur répond pour le demandeur ; un collègue ne peut qu'écrire un message. Ceux qui ont le droit des notes
choisissent, au-dessus du texte, **Message** ou **Note interne**.

## Les fichiers

On joint des fichiers à un ticket, à une tâche ou à un message : images, PDF, documents de bureautique, CSV, ZIP,
jusqu'à 10 Mo chacun (réglable). Le type est vérifié sur le contenu réel du fichier ; un script ou un exécutable
est refusé.

Les fichiers sont rangés à part, jamais accessibles directement : chaque téléchargement vérifie que vous pouvez voir
le ticket et le message. Une image s'affiche en vignette ; un clic l'ouvre.

## Le texte mis en forme

Les descriptions et les messages acceptent le gras, l'italique, les listes, les citations, le code et les liens.
Tout est nettoyé à l'enregistrement : ni script, ni contenu actif, ni lien autre que web ou courriel.

## Sur un ticket transmis

Dans l'application cliente, un message, une réponse ou une note partagée part au Support **au moment où on
l'écrit**. S'il ne répond pas, le message n'est pas enregistré et un avertissement le dit : rien n'est perdu en
silence. Ce que le Support écrit arrive dans l'application cliente quelques instants plus tard. Voir
[La synchronisation](#modal-tma.synchronisation).

## En détail

- [Message ou note interne ?](#modal-tma.chronologie.commentaire-ou-note)
- [Les pièces jointes](#modal-tma.chronologie.fichiers)
- [La mise en forme du texte](#modal-tma.chronologie.mise-en-forme)
