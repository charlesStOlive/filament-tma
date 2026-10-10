---
title: Les membres du Support dans une application cliente
icon: heroicon-o-user-group
order: 25
---

# Les membres du Support dans une application cliente

Une personne du **Support** peut avoir aussi un compte dans une application cliente (le même e-mail des deux côtés,
par exemple `charles@notilac.fr`), et vouloir y travailler directement : répondre, prendre en charge, saisir son
temps, sans repasser par l'application du Support. Une fois **reliée**, elle y intervient **au nom du Support**.

## Ce qui change pour elle, dans l'application cliente

Sur un ticket **traité par le Support** (ou une tâche projet), elle agit **pour le Support** :

- elle a les passages de l'équipe qui traite (Prendre en charge, Demander des précisions, Marquer comme résolu…),
  jamais ceux du demandeur (Répondre, Confirmer et clore, Rouvrir) : elle ne s'écrit jamais à elle-même ;
- ses messages, réponses et notes internes sont écrits **chez le Support, en son nom de là-bas** ; une note y reste (elle
  n'est pas visible dans l'application cliente) ;
- elle saisit son temps (**Saisir du temps**), enregistré chez le Support ;
- elle ne transmet pas de demande au Support : elle en fait partie.

Un bandeau le rappelle sur la fiche : « Vous intervenez ici au nom du Support ». Tout ce qu'elle fait apparaît dans
les deux applications comme fait par elle, avec la mention **Support**.

Sur un ticket **traité par l'équipe du client**, elle est un utilisateur comme les autres de l'application, avec ses
rôles de l'application.

Ses droits sont **ceux qu'elle a chez le Support** : c'est le Support qui exécute et contrôle.

## Relier une personne (Support)

**Applications clientes ›** la fiche de l'application **› Membres du Support › Relier**.

- La personne doit **déjà avoir un compte dans l'application cliente**, avec le même e-mail (et ses rôles de
  l'application).
- Le Support lui émet un **jeton personnel, limité à cette application** (il ne lui ouvre que les tickets et tâches
  de ce client), et l'envoie à l'application. Rien à recopier.
- **Renvoyer** émet un nouveau jeton (l'ancien ne marche plus) ; **Délier** retire le jeton et le lien.

Les membres du Support n'apparaissent pas dans la liste des utilisateurs de l'application que voit le Support : ils
ne sont pas des demandeurs.

## Pourquoi pas le compte de liaison de l'application ?

Le **compte de service** relie les deux applications : c'est l'application cliente qui parle, toujours comme
demandeur, et seulement pour ses tickets (voir [Les comptes de service et leurs jetons](#modal-tma.demarrer.jetons)).
Un membre du Support, lui, parle **en son nom**, avec ses droits du Support. Deux identités, deux jetons : le Support
sait toujours qui agit.
