---
title: La synchronisation
icon: heroicon-o-arrow-path
order: 90
---

# La synchronisation

Un ticket transmis au Support, ou une tâche projet publiée, existe dans les **deux** applications. Le
Support fait **autorité** : en cas de différence, c'est sa version qui compte.

## De l'application cliente vers le Support

Transmettre un ticket, répondre, confirmer, rouvrir, écrire un message, partager une note : l'application cliente l'envoie au
Support **tout de suite**, et attend sa réponse. S'il ne répond pas, **rien n'est fait** et un message le
dit. On réessaie plus tard ; rien ne part en double.

## Du Support vers l'application cliente

Ce que fait le Support (prise en charge, question, message, temps saisi, clôture…) part vers
l'application cliente **par la file d'attente**, en général en quelques secondes. Si elle est indisponible, l'envoi
est retenté plusieurs fois, de plus en plus espacé ; le travail du Support n'est jamais bloqué.

Après le dernier essai, le ticket passe **« Synchronisation en erreur »** (colonne et filtre **Synchro**, et la
dernière erreur sur la fiche). **Renvoyer à l'application** (menu de la fiche) relance l'envoi.

## Ce qui est partagé, et ce qui ne l'est pas

| Partagé | Jamais partagé |
| --- | --- |
| les champs du ticket ou de la tâche | les notes non partagées |
| les changements d'état | le détail des lignes de temps |
| les messages, réponses, notes partagées et leurs fichiers | les brouillons du Support |
| les totaux de temps (global et par mois) | |
| l'état des forfaits | |

## Les utilisateurs

Le Support ne garde pas de copie des utilisateurs d'une application cliente. Il lui demande, quand il en a
besoin, la liste de ceux qui ont accès au Support (gardée une heure) : **Applications clientes ›
Utilisateurs**. Chaque message garde le nom et l'e-mail de son auteur, même si cet utilisateur disparaît ensuite.

Pour relier deux applications : [Configuration de départ](#modal-tma.demarrer).

## En détail

- [Synchronisation en erreur](#modal-tma.synchronisation.erreurs)
