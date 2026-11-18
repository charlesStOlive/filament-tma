---
title: Le commit d'un message
icon: heroicon-o-code-bracket
order: 25
---

# Le commit d'un message

Le Support peut dire **quel commit** porte un message sur un ticket : celui qui contient la correction, ou l'étape dont
parle le message. C'est facultatif.

## Où le mettre

Le champ **Commit** est sous le texte, dans la zone **Nouveau message**, quand c'est le tour du Support : il accompagne
**En cours**, **Demander des précisions** et **Marquer comme résolu**. On y met :

- l'**empreinte** du commit, de 7 à 40 caractères : `704f0e3` ;
- ou son **lien** : `https://github.com/…/commit/704f0e3…` (GitHub, GitLab, Bitbucket…).

Un commit accompagne un message : sans texte, il est refusé.

Pour le changer ou le retirer, **Modifier** le message (tant qu'il est le dernier) : le champ **Commit** y est aussi ;
vide, il n'y a plus de commit.

## Où il se lit

- Dans l'historique, en tête du message : l'empreinte courte. Un lien s'ouvre dans un nouvel onglet ; une empreinte
  seule se lit telle quelle.
- Dans le volet de droite, **Commits** : tous ceux du ticket, du premier au dernier.

Le demandeur les voit aussi, dans son application. Seuls les messages du Support portent un commit.

## En ligne de commande

`php artisan tma:comment DES-0042 --body="…" --commit=704f0e3` écrit un message **En cours** porté par ce commit
(voir [Les commandes](#modal-tma.commandes)).
