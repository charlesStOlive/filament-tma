---
title: La synchronisation
icon: heroicon-o-arrow-path
order: 90
---

# La synchronisation

Un ticket transmis au Support, ou une tâche projet publiée, existe dans les **deux** applications. Le
Support fait **autorité** : en cas de différence, c'est sa version qui compte.

## De l'application cliente vers le Support

Transmettre un ticket, répondre, confirmer, renvoyer au Support, écrire ou modifier un message, ajouter, archiver ou
supprimer une note : l'application cliente l'envoie au Support **tout de suite**, et attend sa réponse. S'il ne répond pas, **rien n'est fait** et un message le
dit. On réessaie plus tard ; rien ne part en double.

## Du Support vers l'application cliente

Ce que fait le Support (question, résolu, note, temps saisi, clôture…) part vers
l'application cliente **par la file d'attente**, en général en quelques secondes. Si elle est indisponible, l'envoi
est retenté plusieurs fois, de plus en plus espacé ; le travail du Support n'est jamais bloqué.

Après le dernier essai, le ticket passe **« Synchronisation en erreur »** (colonne et filtre **Synchro**, et la
dernière erreur sur la fiche). **Renvoyer à l'application** (menu de la fiche) relance l'envoi.

## Ce qui est partagé, et ce qui ne l'est pas

| Partagé | Jamais partagé |
| --- | --- |
| les champs du ticket ou de la tâche, et leurs pièces jointes | le détail des lignes de temps |
| un projet (fourchette, participants), avec ses tâches publiées | |
| les changements d'état | les brouillons du Support |
| les messages, réponses, notes et leurs fichiers (modifiés, archivés, supprimés compris) | |
| les totaux de temps (global et par mois) | |
| l'état des forfaits | |

## Les utilisateurs

Le Support ne garde pas de copie des utilisateurs d'une application cliente. Il lui demande, quand il en a
besoin, la liste de ceux qui ont accès au Support (gardée une heure) : **Applications clientes ›
Utilisateurs**. Chaque message garde le nom et l'e-mail de son auteur, même si cet utilisateur disparaît ensuite.

Un nom est une **copie**, rafraîchie quand la personne agit : quelqu'un qui change de nom n'apparaît sous le nouveau
qu'à sa prochaine action, ou après un renvoi (ci-dessous).

Pour relier deux applications : [Configuration de départ](#modal-tma.demarrer).

## Tout renvoyer

Chez le Support, pour remettre d'accord les deux applications (après une panne, une mise à jour, un changement de
nom) :

- un ticket ou une tâche : **Renvoyer à l'application**, dans le menu de sa fiche ;
- un projet : **Renvoyer à l'application** sur sa fiche (ses tâches publiées repartent, avec lui) ;
- une application entière : **Applications clientes › (l'application) › Tout renvoyer** ; les noms sont d'abord
  remis à jour (ceux du Support, et ceux des correspondants de l'application) ;
- en ligne de commande, dans le maître : `php artisan tma:resync --all` (ou `--instance=`, `--project=`, `--ticket=`,
  `--task=` ; `--names` pour les noms seulement ; `--dry-run` pour voir ce qui partirait).

Les envois passent par la file d'attente. Rien n'est perdu ni dédoublé : ce qui est déjà là est mis à jour. Il faut le
droit de relancer une synchronisation (`sync`) ; « Tout renvoyer », celui de modifier l'application.

## En détail

- [Synchronisation en erreur](#modal-tma.synchronisation.erreurs)
