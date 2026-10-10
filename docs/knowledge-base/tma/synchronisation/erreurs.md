---
title: Synchronisation en erreur
icon: heroicon-o-exclamation-triangle
order: 10
---

# Synchronisation en erreur

La colonne **Synchro** (et la fiche, **Suivi › Synchro**) dit où en est la copie d'un ticket ou d'une tâche chez
l'autre application :

| Statut | Ce qu'il veut dire |
| --- | --- |
| Non partagé | le ticket n'est pas partagé : l'équipe du client le traite |
| En attente de synchronisation | un envoi est en cours, ou va être retenté |
| Synchronisé | l'autre application a la dernière version |
| Synchronisation en erreur | l'envoi a échoué après plusieurs essais |

## Quand ça arrive

Le Support envoie ses changements par la **file d'attente**, et réessaie plusieurs fois, de plus en plus
espacé (de 30 secondes à une heure). Après le dernier essai, le ticket passe **en erreur**, et la dernière erreur
s'affiche sous le statut (application arrêtée, adresse ou jeton changés…).

## Quoi faire

1. Vérifier l'application cliente : **Applications clientes › Tester**.
2. Corriger (adresse, jeton, application redémarrée…).
3. **Renvoyer à l'application** (menu de la fiche) : l'état complet repart.

Rien n'est perdu : chaque envoi porte tout l'état du ticket, et l'autre application ne garde jamais deux fois la même
chose.

Dans l'autre sens (de l'application cliente vers le Support), il n'y a pas d'erreur différée : l'action
échoue tout de suite, avec un message, et rien n'est enregistré.
