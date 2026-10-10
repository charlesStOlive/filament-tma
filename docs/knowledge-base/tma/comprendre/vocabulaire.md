---
title: Les mots du module
icon: heroicon-o-language
order: 5
---

# Les mots du module

Le module emploie les mots des outils de support les plus courants. Quelques-uns dépendent du client.

## Les personnes et les équipes

| Mot | Ce qu'il désigne |
| --- | --- |
| **Demandeur** | la personne qui a ouvert la demande |
| **Support** | l'équipe qui fait la maintenance, et son application (Filbreeze) |
| **Équipe EDM** | les personnes du client (ici, EDM) en lien avec le Support : elles traitent les demandes des autres, ou les transmettent au Support |
| **Pris en charge par** | la personne qui s'occupe de la demande |
| **Intervenant** | celui qui a fait une saisie de temps, une intervention précise |
| **Membre du Support** | une personne du Support qui travaille aussi depuis l'application d'un client (voir [Les membres du Support](#modal-tma.agents)) |

## Le nom court du client

Chaque application cliente a un **nom court** : « EDM » pour Enfants du Mékong. Il forme :

- **Équipe EDM** : dans **Traité par** et **En attente de** ;
- **Confier à l'équipe EDM** : le passage qui confie un brouillon à l'équipe du client ;
- **Publier chez EDM** : le passage par lequel le Support ouvre un ticket ou une tâche au client.

Dans la chronologie, il signale aussi d'où vient une personne : « EDM » à côté d'un nom, vu du Support ; « Support »,
vu de l'application du client.

Le nom court se règle chez le Support, dans **Applications clientes › la fiche de l'application**. Il part chez le
client avec la commande de **Configuration de l'application** (`--short-name`). Pour le changer ensuite, modifiez
la fiche, puis, dans l'application cliente, la ligne `TMA_INSTANCE_SHORT_NAME` de son `.env`.

Sans nom court, le module dit « Équipe locale » et « Publier chez le client ».

## Les états d'un ticket

| État | En attente de | Ce qui se passe |
| --- | --- | --- |
| Brouillon | son auteur | en préparation, invisible des autres |
| Nouveau | l'équipe qui traite | attend d'être pris en charge |
| En cours | l'équipe qui traite | la demande est traitée |
| En attente du demandeur | le demandeur | des précisions lui ont été demandées |
| En pause | l'équipe qui traite | suspendu jusqu'à une date, puis repris tout seul |
| Résolu | le demandeur | le travail est fait : il confirme, ou rouvre |
| Clos | — | terminé ; c'est l'état qui permet la facturation complète |
| Annulé | — | abandonné ou refusé, avec son motif |

## Changer un mot

Tous les textes du module passent par les traductions de Laravel. Une application peut en remplacer un dans son
fichier `lang/fr.json`, sans toucher au module :

```json
{
    "Résolu": "Terminé, à confirmer"
}
```
