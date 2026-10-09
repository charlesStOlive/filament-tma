---
title: Les comptes de service et leurs jetons
icon: heroicon-o-key
order: 30
---

# Les comptes de service et leurs jetons

Les deux applications s'appellent avec des **jetons** (Passport), chacun lié à un **compte de service** : un
utilisateur sans rôle, qui n'entre dans aucun écran et ne sert qu'à cela.

| Compte | Où il existe | Son jeton est utilisé par | Où le jeton est rangé |
| --- | --- | --- | --- |
| celui de l'application cliente (`desapp@tma.local`) | le centre de service | l'application cliente | son `.env` : `TMA_MASTER_TOKEN` |
| celui du centre de service (`centre-de-service@tma.local`) | l'application cliente | le centre de service | la fiche de l'application : **Jeton émis par l'application** (chiffré) |

## Les créer

- **Configuration de l'application** (centre de service) crée le premier compte et son jeton, et donne la commande à
  lancer dans l'application cliente.
- Cette commande (`tma:install --master-account=…`) crée le second compte, et **affiche son jeton** : collez-le dans la
  fiche, champ **Jeton émis par l'application**.

## Ce qu'il faut savoir

- Un jeton n'est **affiché qu'une fois**. Perdu ? Relancez simplement l'étape qui le crée : un nouveau jeton est émis,
  **l'ancien ne marche plus**, et l'autre application doit recevoir le nouveau.
- Le centre de service reconnaît une application cliente **à son jeton**, jamais à ce qu'elle déclare : elle ne peut
  voir ni toucher les tickets d'une autre.
- Un jeton ne se met jamais dans un fichier versionné.
- Désactiver une application (**Active**) coupe son accès sans supprimer son compte.
