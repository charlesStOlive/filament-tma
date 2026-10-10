---
title: Message ou note interne ?
icon: heroicon-o-chat-bubble-left-right
order: 10
---

# Message ou note interne ?

| | Message | Note interne |
| --- | --- | --- |
| Pour qui | l'**échange** avec tout le monde sur le ticket | votre **équipe** |
| Qui la lit | tout le monde, demandeur compris | seulement l'application où elle est écrite, et ceux qui ont le droit des notes |
| Change l'état ? | non | non |

Une note interne sert à écrire ce qu'on ne dit pas au demandeur : une piste technique pas encore vérifiée, l'accès
d'un compte de test, un point de facturation.

## Partager une note

**Partager**, sous une note interne, la rend visible de l'**autre équipe** : l'équipe du client si vous êtes au
Support, le Support si vous êtes chez le client. Le demandeur ne la lit toujours pas. Ce partage **ne se retire
pas**.

## Où les écrire

Dans la **zone de réponse**, sous la chronologie : on choisit **Message** ou **Note interne** au-dessus du texte.
« Répondre » n'est pas une troisième sorte d'entrée : c'est le message par lequel le demandeur répond quand on
l'attend, et qui rend la main. Voir [La chronologie](#modal-tma.chronologie).

## Sur un ticket terminé

On n'écrit plus de message ; les notes internes restent possibles.

## Les réglages

Chaque application règle les notes dans `config/filament-tma.php` (`notes`) :

- `TMA_NOTES_SHARING=false` retire le partage : une note ne quitte jamais son application ;
- `TMA_NOTES=false` retire les notes internes : il n'y a plus que des messages. Utile quand une seule personne
  intervient pour un client.
