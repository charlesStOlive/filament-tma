---
title: Les notes
icon: heroicon-o-pencil-square
order: 10
---

# Les notes

Une note se pose **à côté** de l'échange : une piste, une remarque, une capture, un rappel. Elle commente **un
message** en particulier, ou **le ticket entier**. Elle ne répond à personne et ne change pas l'état du ticket :
elle s'ajoute, quelle que soit la partie qui a la main.

| | Message | Note |
| --- | --- | --- |
| Pour quoi | l'**échange** : questions, réponses, précisions | un commentaire à côté, sur un message ou sur le ticket |
| Qui la lit | tout le monde sur le ticket, des deux côtés | tout le monde, des deux côtés ; ou son auteur seul s'il la garde pour lui |
| Où l'écrire | **Nouveau message**, sous l'historique | **Ajouter une note**, sous le message ou sous la demande |
| Change l'état ? | une réponse, oui ; un simple message, non | non |

Par défaut, ce qu'on écrit dans une note, tout le monde peut le lire, demandeur compris.

## Une note pour soi

**Visible de moi seul**, en écrivant ou en modifiant la note, la cache aux autres : elle ne se montre qu'à son
auteur, des deux côtés (un membre du Support la retrouve dans l'application cliente), marquée d'un cadenas et de
« Visible de vous seul ». Elle prend un numéro comme les autres (les autres voient donc un numéro manquer) et ne
change pas la dernière activité du ticket. On la **montre à tous** plus tard en décochant la case.

## Les reconnaître

Chaque note porte un **numéro** dans le ticket (n° 1, n° 2…) et le **nom de son auteur** : « n° 3 · Ada
Lovelace ». Une note supprimée garde son numéro : il n'est pas redonné.

- Sous chaque message, ses notes s'affichent en **vignettes** ; celles sur le ticket entier, sous la demande.
- Dans le volet de droite, l'onglet **Notes** les montre **toutes** en aperçu : numéro, auteur, ce qu'elles
  commentent, le début du texte. **Voir les archivées** les ajoute.

## Les ouvrir

Une note s'ouvre toujours dans une **fenêtre** :

- une note sur un **message** ouvre une grande fenêtre : le message à gauche, avec ses fichiers en vignettes, et ses
  notes à droite ;
- une note sur le **ticket** ouvre la même fenêtre, avec la demande à gauche.

On y lit les notes ; on y **ajoute** une note, et ses boutons ouvrent une petite fenêtre par-dessus : **Modifier**,
**Archiver**, **Supprimer**.

## Le texte et les images

Une note a un texte mis en forme et peut avoir des **images** (captures d'écran, photos), dans les types acceptés
pour les pièces jointes. Une note peut n'être qu'une image.

## Qui fait quoi

| | Qui |
| --- | --- |
| écrire une note | ceux qui ont le droit `note` (voir [Les droits](#modal-tma.droits)) |
| la **modifier**, la **supprimer** | son auteur seul |
| l'**archiver**, la ressortir | quiconque peut écrire des notes ; une note pour soi, son auteur |

**Archiver** une note la range : on n'a plus besoin de la lire, mais elle reste consultable (**Voir les
archivées**, ou la vignette « notes archivées » sous le message). **Ressortir** la remet en place.

Les notes restent possibles sur un ticket terminé.

## Le réglage

`TMA_NOTES=false`, dans le `.env` d'une application, retire les notes : il n'y a plus que des messages.
