---
title: Les adresses des applications
icon: heroicon-o-globe-alt
order: 20
---

# Les adresses des applications

Les deux applications s'appellent l'une l'autre : chacune doit connaître l'**adresse** de l'autre, **telle qu'on la
joint depuis l'autre**.

| Adresse | Où on la renseigne | Qui l'appelle |
| --- | --- | --- |
| celle de l'application cliente | dans le centre de service : **Applications clientes › Adresse** | le centre de service |
| celle du centre de service | dans l'application cliente : `TMA_MASTER_URL` (donnée par **Configuration de l'application**) | l'application cliente |

Une adresse est la racine du site (`https://desapp.example`), sans `/admin` : le module y ajoute lui-même
`/api/tma/v1/…`.

## En production

Les adresses publiques, en `https://`.

## En développement (Sail, Docker)

Chaque application tourne dans son propre conteneur : `localhost` y désigne le conteneur lui-même, **pas** l'autre
application. Il faut :

- un **réseau Docker commun** aux deux (déclaré dans les deux `compose.yaml`) ;
- l'adresse du **conteneur** de l'autre application sur ce réseau, par exemple `http://filbreeze.test` ou
  `http://laravel.test` selon son nom de service ou son alias.

Le bouton **Tester** de la fiche vérifie l'adresse de l'application cliente ; si l'application cliente n'arrive pas
au centre de service, la transmission d'un ticket le dira.
