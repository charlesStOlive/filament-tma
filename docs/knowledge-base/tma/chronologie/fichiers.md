---
title: Les pièces jointes
icon: heroicon-o-paper-clip
order: 20
---

# Les pièces jointes

On joint des fichiers à un ticket, à une tâche projet ou à un message : jusqu'à **10 fichiers**, de **10 Mo** chacun
(réglable).

## Les types acceptés

Images (PNG, JPEG, GIF, WebP), PDF, texte et CSV, **Markdown** (`.md`), ZIP, documents Word (`.docx`), Excel
(`.xlsx`), PowerPoint (`.pptx`) et OpenDocument.

Le type est vérifié sur le **contenu réel** du fichier, et l'extension doit aller avec : un faux PDF, une page HTML,
un script, un exécutable, un SVG sont refusés.

## Pas de macro

Un document Word, Excel, PowerPoint ou OpenDocument est **refusé s'il contient des macros** ou du contenu actif
(contrôle ActiveX, objet incorporé, modèle chargé depuis Internet), même renommé (un `.xlsm` rebaptisé `.xlsx`). Un
message dit pourquoi : enregistrez le document sans macro (`.docx`, `.xlsx`, `.pptx`) et joignez-le de nouveau. Les
anciens formats (`.doc`, `.xls`, `.ppt`) et les documents protégés par un mot de passe ne sont pas acceptés : on ne
peut pas vérifier ce qu'ils contiennent.

Un fichier **Markdown** reste du texte, même s'il contient des balises : il se télécharge et s'ouvre dans votre
éditeur, il ne s'affiche jamais comme une page.

## Qui peut les ouvrir

Les fichiers sont rangés à part, jamais accessibles par une adresse publique : chaque ouverture vérifie que vous
pouvez voir le ticket ; le fichier d'une note supprimée ne s'ouvre plus.

Une image s'affiche en vignette ; les autres fichiers (PDF compris) se téléchargent, sans jamais s'exécuter dans
l'application.

## Voir une image

Un clic sur la vignette ouvre l'image **par-dessus la page**, sans la télécharger, qu'elle soit jointe à la demande,
à un message ou à une note (aussi dans la fenêtre des notes) :

- les **flèches** (à l'écran ou au clavier) passent aux autres images du même message ;
- un clic sur l'image, ou la **loupe**, la montre en **taille réelle** (elle défile) ; un second clic l'ajuste à
  l'écran ;
- **Plein écran**, **Ouvrir dans un onglet** et **Télécharger** sont en haut à droite ;
- **Échap**, la croix ou un clic à côté de l'image la referment, sans fermer la fenêtre ouverte dessous.

Un clic du milieu (ou Ctrl + clic) sur la vignette ouvre l'image dans un nouvel onglet, là aussi sans téléchargement.

## Entre les applications

Les fichiers d'un message partagé suivent le message chez l'autre application, qui les contrôle de la même façon.
