---
title: Les forçages
icon: heroicon-o-bolt
order: 65
---

# Les forçages

Dans le circuit normal, seule la partie dont c'est le tour agit (voir [Qui doit agir ?](#modal-tma.comprendre.la-main)).
Les **forçages** sont des actions à part, hors du tour, chacune avec **son propre droit**, réservé au Support. Elles
apparaissent en haut de la fiche, en gris.

| Forçage | Depuis | Résultat | Motif | Droit |
| --- | --- | --- | --- | --- |
| **Forcer la clôture** | tout état ouvert après le brouillon : Nouveau, En cours, En pause, En attente du demandeur, Résolu | Clos | obligatoire | `tma.ticket.force-close` |
| **Forcer le retour** | En attente du demandeur, Résolu | En cours : la main revient au Support | facultatif | `tma.ticket.force-return` |

## Quand s'en servir

- **Forcer la clôture** : un ticket qui dort et dont plus personne ne s'occupe ; une demande restée sans réponse que
  l'on ne veut pas laisser attendre la [clôture automatique](#modal-tma.tickets.cloture-automatique).
- **Forcer le retour** : le Support a trouvé la réponse ailleurs, ou veut reprendre le travail sans attendre le
  demandeur.

Le passage reste dans l'historique, avec son auteur et son motif, et les destinataires en sont prévenus.

## Qui les a

Ces droits se donnent dans l'écran des rôles, comme les autres. Ils sont faits pour le Support et les administrateurs ;
rien n'empêche techniquement de donner **Forcer la clôture** à un correspondant du client, mais c'est à éviter.
