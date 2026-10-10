---
title: L'audit et le nettoyage
icon: heroicon-o-shield-check
order: 120
---

# L'audit et le nettoyage

## Le journal d'audit

**Support › Journal d'audit** retient les actions sensibles, en lecture seule :

- toute saisie, modification ou suppression de **temps** ;
- les changements des **champs** d'un ticket ou d'une tâche ;
- les **applications clientes** et les **forfaits** (créés, modifiés, supprimés), les **seuils** franchis ;
- les **synchronisations** reçues ou en échec ;
- les **nettoyages**.

Pour chacune : qui, depuis quelle application, quand, sur quoi, les valeurs **avant** et **après** (bouton
**Détail**), et l'origine : interface, API, commande ou tâche automatique. Les jetons ne sont jamais écrits.

Les changements d'état ne sont pas dans le journal : chaque ticket et chaque tâche en garde l'historique dans sa
[chronologie](#modal-tma.chronologie).

## Le nettoyage

La commande `tma:cleanup` supprime **définitivement**, après 90 jours (réglable) :

- les tickets et les tâches supprimés, avec leur chronologie, leur temps et leurs fichiers ;
- les messages supprimés ;
- les fichiers dont le ticket ou le message n'existe plus ;
- les fichiers envoyés mais jamais rattachés à un message.

Elle ne touche **jamais** à ce qui a du temps rattaché à une facture. `--dry-run` montre ce qu'elle ferait sans rien
supprimer : à lancer d'abord. Chaque passage est journalisé et inscrit dans l'audit. Elle ne tourne d'elle-même
(chaque dimanche à 3 h) que si l'application est réglée pour.
