---
title: Les forfaits
icon: heroicon-o-chart-pie
order: 70
---

# Les forfaits

Un forfait est un volume de TMA alloué à un client pour une période.

## Créer un forfait (centre de service)

**TMA › Forfaits › Nouveau** :

- **Client**, et éventuellement une **application** : sans application, le forfait est **partagé** entre toutes
  celles du client ; avec, il ne vaut que pour elle ;
- **Contrat** (facultatif) ;
- **Rythme** : mensuel ou trimestriel ;
- **Volume par période**, en heures ou en jours ;
- **Du / Au** : la durée du forfait.

## Ce qui consomme le forfait

Le temps **facturable** saisi sur les **tickets** des applications couvertes, dans la période. Le temps non
facturable et celui des tâches projet n'y comptent pas (réglable).

La liste des forfaits montre, pour la période en cours : l'alloué, le consommé (et son pourcentage), le restant.
**Détail** donne la répartition par mois.

## Les seuils

À **80, 90, 100 et 110 %**, le franchissement d'un seuil est signalé **une seule fois par période** : un courriel
part aux adresses configurées, et l'application peut y réagir. 100 % veut dire « entièrement consommé », au-delà
« dépassé ».

**Réinitialiser les seuils** permet de les signaler de nouveau pour la période en cours.

Un dépassement ne bloque pas la saisie du temps, sauf si l'application est réglée pour cela.

## Dans l'application cliente

La consommation du forfait s'affiche en tête de la liste des tickets : consommé sur alloué, pourcentage, restant ou
dépassement, et l'évolution par mois. Elle se met à jour après chaque saisie de temps du centre de service.

## En détail

- [Un forfait pour un client ou une application](#modal-tma.forfaits.portee)
- [Volume et rythme d'un forfait](#modal-tma.forfaits.volume)
- [Les seuils d'un forfait](#modal-tma.forfaits.seuils)
