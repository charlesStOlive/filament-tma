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
| **Demandeur** | la personne du client qu'on attend sur une demande : celle qui l'a ouverte, ou celle qui a pris la main |
| **Support** | l'équipe qui fait la maintenance, et son application (Filbreeze) |
| **Correspondant du Support** | une personne du client qui a le droit d'écrire au Support : elle lui transmet ses demandes, et le Support peut la nommer demandeur ou destinataire |
| **Contact au Support** | le membre du Support qui suit la demande, choisi par le demandeur en la transmettant (d'office s'il n'y en a qu'un) |
| **Intervenant** | celui qui a fait une saisie de temps, une intervention précise |
| **Membre du Support** | une personne du Support (le droit **Membre du Support**) ; reliée à un client, elle travaille aussi depuis son application (voir [Les membres du Support](#modal-tma.agents)) |
| **Forçage** | une action hors du tour, avec son propre droit : **Forcer la clôture**, **Forcer le retour** |

## Le nom court du client

Chaque application cliente a un **nom court** : « EDM » pour Enfants du Mékong. Il forme :

- **Publier chez EDM** : le passage par lequel le Support ouvre un ticket ou une tâche au client ;
- **Correspondants EDM** : vus du Support, les personnes du client qu'il peut nommer.

Dans l'historique, il signale aussi d'où vient une personne : « EDM » à côté d'un nom, vu du Support ; « Support »,
vu de l'application du client.

Le nom court se règle chez le Support, dans **Applications clientes › la fiche de l'application**. Il part chez le
client avec la commande de **Configuration de l'application** (`--short-name`). Pour le changer ensuite, modifiez
la fiche, puis, dans l'application cliente, la ligne `TMA_INSTANCE_SHORT_NAME` de son `.env`.

Sans nom court, le module dit « Publier chez le client » et « Correspondants du client ».

## Les états d'un ticket

| État | En attente de | Ce qui se passe |
| --- | --- | --- |
| Brouillon | son auteur | en préparation, invisible des autres |
| Nouveau | le Support | transmis : attend la première réponse du Support |
| En cours | le Support | la demande est traitée |
| En attente du demandeur | le demandeur | des précisions lui ont été demandées |
| En pause | le Support | suspendu jusqu'à une date, puis repris tout seul |
| Résolu | le demandeur | le travail est fait : il confirme, ou renvoie la demande |
| Clos | — | terminé ; c'est l'état qui permet la facturation complète |
| Annulé | — | retiré par son demandeur ou refusé par le Support, avec son motif |

## Changer un mot

Tous les textes du module passent par les traductions de Laravel. Une application peut en remplacer un dans son
fichier `lang/fr.json`, sans toucher au module :

```json
{
    "Résolu": "Terminé, à confirmer"
}
```
