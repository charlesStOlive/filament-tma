---
title: L'identifiant d'une application
icon: heroicon-o-finger-print
order: 10
---

# L'identifiant d'une application

Chaque installation du module a un **identifiant** : 26 lettres et chiffres (un ULID), par exemple
`01K9ZQ8M4Q7V2C3E5R6T8Y0B1N`. Il est dans son `.env`, `TMA_INSTANCE_UID`.

## À quoi il sert

- Il est **écrit dans chaque ticket** créé par l'application : c'est ainsi que le centre de service sait d'où vient un
  ticket, et qu'une application cliente reconnaît les siens.
- Le centre de service le connaît pour chaque application cliente (**Applications clientes**, champ **Identifiant**).

Les deux doivent être **le même** : celui du `.env` de l'application cliente, et celui de sa fiche dans le centre de
service.

## Il ne change plus

Une fois des tickets créés ou reçus, l'identifiant est **définitif** : le changer couperait le lien avec eux. Le
bouton qui en génère un nouveau disparaît alors de la fiche, et `tma:install` refuse de le modifier.

## Le plus simple

Laissez le centre de service le générer : la fiche d'une nouvelle application cliente le propose d'office, et
**Configuration de l'application** donne la commande qui l'écrit dans le `.env` de l'application cliente.

Si l'application cliente a déjà le sien (installée avant), collez-le dans la fiche à la place de celui proposé.

## Le préfixe des numéros

À côté, `TMA_TICKET_PREFIX` donne le début des **numéros lisibles** des tickets de l'application : `DES` donne
`DES-0042`. Choisissez-le court et différent pour chaque application : c'est lui qu'on lit dans les listes et les
courriels. Il peut changer ; les numéros déjà donnés restent.
