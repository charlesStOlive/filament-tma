---
title: Corriger l'orthographe
icon: heroicon-o-sparkles
order: 40
---

# Corriger l'orthographe

Le bouton **Orthographe** fait relire un texte par l'intelligence artificielle avant de l'envoyer. Il se trouve :

- dans le **nouveau message**, à côté des boutons d'envoi ;
- en haut du formulaire d'un **ticket** ou d'une **tâche**, à la création comme à la modification : il relit le titre
  et la description.

## Comment ça marche

1. **Orthographe** ouvre une fenêtre avec le texte tel qu'il partira. Rien n'est envoyé à l'IA tant que vous n'avez
   pas cliqué sur **Soumettre à l'IA**.
2. La fenêtre montre ensuite les corrections, mot par mot. **Affiner** en demande une autre version, en disant ce qui
   ne va pas (« garde le tutoiement ») ; **Ignorer** les écarte.
3. **Tout appliquer**, ou **Appliquer la sélection** pour les seuls champs cochés, remet le texte corrigé dans le
   champ, comme si vous l'aviez tapé. Il n'est pas encore envoyé : relisez-le, puis envoyez le message ou enregistrez
   le formulaire.

Un champ vide, ou qu'on ne peut plus modifier (la description d'une demande déjà suivie d'un message, par exemple),
n'est pas relu.

## Qui le voit

Le bouton n'apparaît que si :

- l'application a une **clé** pour le service d'IA. Sans clé, il n'y a pas de bouton du tout ;
- vous avez le droit **Corriger l'orthographe avec l'IA** : `tma.ticket.ai.orthography` sur les tickets,
  `tma.task.ai.orthography` sur les tâches. Il se donne dans l'écran des rôles, même avant que la clé existe : le
  jour où elle arrive, le bouton s'affiche pour qui l'a.

Chaque relecture est enregistrée, avec ce qu'elle a coûté, parmi les demandes IA de l'application.
