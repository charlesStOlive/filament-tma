---
title: Traitement local ou centre de service
icon: heroicon-o-arrows-right-left
order: 20
---

# Traitement local ou centre de service

La fiche d'un ticket l'affiche dans **Suivi › Traitement**.

## Traitement local

La demande reste dans son application. Un collègue la traite : il la **prend en charge**, pose ses questions, la
**soumet à validation**. Le centre de service n'en sait rien.

C'est le mode de départ de toute demande, et le seul pour les utilisateurs qui n'ont pas accès au centre de service.

## Centre de service

**Transmettre au centre de service** envoie la demande au maître (Filbreeze), qui la traite et **fait autorité** :

- ses champs ne se modifient plus dans l'application d'origine ;
- les passages du demandeur (Répondre, Valider, Rouvrir…) sont faits par le centre de service, à la demande de
  l'application ;
- tout ce qui est partagé est copié d'une application à l'autre (voir [La synchronisation](#modal-tma.synchronisation)).

On transmet un brouillon (le demandeur), ou un ticket déjà en traitement local (l'intervenant, qui escalade). Le ticket
repart alors en **Soumis**, chez le centre de service. Il ne revient jamais en traitement local.

Si le centre de service ne répond pas, la transmission est annulée et un message le dit : réessayez plus tard.
