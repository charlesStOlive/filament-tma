---
title: La synchronisation
icon: heroicon-o-arrow-path
order: 90
---

# La synchronisation

Un ticket transmis au centre de service, ou une tâche projet publiée, existe dans les **deux** applications. Le
centre de service fait **autorité** : en cas de différence, c'est sa version qui compte.

## De l'application cliente vers le centre de service

Transmettre un ticket, répondre, valider, rouvrir, commenter, partager une note : l'application cliente l'envoie au
centre de service **tout de suite**, et attend sa réponse. S'il ne répond pas, **rien n'est fait** et un message le
dit. On réessaie plus tard ; rien ne part en double.

## Du centre de service vers l'application cliente

Ce que fait le centre de service (prise en charge, question, commentaire, temps saisi, clôture…) part vers
l'application cliente **par la file d'attente**, en général en quelques secondes. Si elle est indisponible, l'envoi
est retenté plusieurs fois, de plus en plus espacé ; le travail du centre de service n'est jamais bloqué.

Après le dernier essai, le ticket passe **« Synchronisation en erreur »** (colonne et filtre **Synchro**, et la
dernière erreur sur la fiche). **Renvoyer à l'application** (menu de la fiche) relance l'envoi.

## Ce qui est partagé, et ce qui ne l'est pas

| Partagé | Jamais partagé |
| --- | --- |
| les champs du ticket ou de la tâche | les notes non partagées |
| les changements d'état | le détail des lignes de temps |
| les commentaires, réponses, résumés, notes partagées et leurs fichiers | les brouillons du centre de service |
| les totaux de temps (global et par mois) | |
| l'état des forfaits | |

## Les utilisateurs

Le centre de service ne garde pas de copie des utilisateurs d'une application cliente. Il lui demande, quand il en a
besoin, la liste de ceux qui ont accès au centre de service (gardée une heure) : **Applications clientes ›
Utilisateurs**. Chaque message garde le nom et l'e-mail de son auteur, même si cet utilisateur disparaît ensuite.

Pour relier deux applications : [Configuration de départ](#modal-tma.demarrer).

## En détail

- [Synchronisation en erreur](#modal-tma.synchronisation.erreurs)
