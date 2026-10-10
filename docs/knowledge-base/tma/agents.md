---
title: Les agents du centre de service
icon: heroicon-o-user-group
order: 25
---

# Les agents du centre de service

Un **agent** est une personne de l'équipe du centre de service qui a aussi un compte dans une application cliente
(le même e-mail des deux côtés, par exemple `charles@notilac.fr`), et qui veut y travailler directement : répondre,
prendre en charge, saisir son temps, sans passer par le centre de service.

## Ce qui change pour lui, dans l'application cliente

Sur un ticket **transmis au centre de service** (ou une tâche projet), il agit **pour le centre de service** :

- il a les passages de l'intervenant (Prendre en charge, Demander une réponse, Soumettre à validation…), jamais ceux
  du demandeur (Répondre, Valider, Rouvrir) : il ne s'écrit jamais à lui-même ;
- ses commentaires, réponses et notes sont écrits **dans le centre de service, en son nom de là-bas** ; une note y
  reste (elle n'est pas visible dans l'application cliente) ;
- il saisit son temps (**Saisir du temps**), enregistré dans le centre de service ;
- il ne transmet pas de demande au centre de service : il en fait partie.

Un bandeau le rappelle sur la fiche : « Vous agissez ici pour le centre de service ». Tout ce qu'il fait apparaît
dans les deux applications comme fait par lui, membre du centre de service.

Sur un ticket **en traitement local**, il est un utilisateur comme les autres de l'application, avec ses rôles de
l'application.

Ses droits sont **ceux qu'il a dans le centre de service** : c'est le centre de service qui exécute et contrôle.

## Relier un agent (centre de service)

**Applications clientes ›** la fiche de l'application **› Agents du centre de service › Relier un agent**.

- L'agent doit **déjà avoir un compte dans l'application cliente**, avec le même e-mail (et ses rôles de
  l'application).
- Le centre de service lui émet un **jeton personnel, limité à cette application** (il ne lui ouvre que les tickets et
  tâches de ce client), et l'envoie à l'application. Rien à recopier.
- **Renvoyer** émet un nouveau jeton (l'ancien ne marche plus) ; **Délier** retire le jeton et le lien.

Les agents n'apparaissent pas dans la liste des utilisateurs de l'application que voit le centre de service : ils ne
sont pas des demandeurs.

## Pourquoi pas le compte de liaison de l'application ?

Le **compte de service** relie les deux applications : c'est l'application cliente qui parle, toujours comme
demandeur, et seulement pour ses tickets (voir [Les comptes de service et leurs jetons](#modal-tma.demarrer.jetons)).
Un agent, lui, parle **en son nom**, avec ses droits du centre de service. Deux identités, deux jetons : le centre de
service sait toujours qui agit.
