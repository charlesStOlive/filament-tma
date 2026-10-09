---
title: Les comptes de service et leurs jetons
icon: heroicon-o-key
order: 30
---

# Les comptes de service et leurs jetons

Les deux applications s'appellent avec des **jetons** (Passport), chacun lié à un **compte de service** : un
utilisateur sans rôle, qui n'entre dans aucun écran et ne sert qu'à cela.

| Compte | Où il existe | Créé par | Son jeton sert à | Où le jeton est rangé |
| --- | --- | --- | --- | --- |
| celui de l'application cliente (`tma-desapp@tma.local`) | le centre de service | la création de l'application dans le centre de service | l'application cliente, pour appeler le centre de service | son `.env` : `TMA_MASTER_TOKEN` |
| celui du centre de service (`centre-de-service@tma.local`) | l'application cliente | la commande `tma:install` | le centre de service, pour appeler l'application | sa fiche : **Jeton de l'application** (chiffré) |

## Rien à recopier

- **Configuration de l'application** (centre de service) émet le premier jeton et le met dans la commande à lancer
  dans l'application cliente.
- Cette commande crée le second compte et **transmet son jeton au centre de service**, qui vérifie aussitôt la
  liaison. La fiche affiche alors « Reçu ».
- Ce n'est qu'en cas d'échec que la commande affiche ce jeton, à coller dans **Saisir le jeton à la main**.

## Ce qu'il faut savoir

- Un jeton n'est **affiché qu'une fois**. Perdu ? Relancez « Configuration de l'application » et sa commande : de
  nouveaux jetons remplacent les anciens, qui ne marchent plus.
- Le centre de service reconnaît une application cliente **à son jeton**, jamais à ce qu'elle déclare : elle ne peut
  voir ni toucher les tickets d'une autre.
- Un compte de service n'est jamais le compte d'une personne : le centre de service le crée lui-même, et refuse
  un compte qui a des rôles.
- Un jeton ne se met jamais dans un fichier versionné.
- Désactiver une application (**Active**) coupe son accès sans supprimer son compte.
