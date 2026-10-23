---
title: Rappels et notifications
icon: heroicon-o-bell
order: 80
---

# Rappels et notifications

## Un ticket engage, une tâche informe

- Un **ticket** attend toujours quelqu'un : le Support quand une demande arrive ou qu'on lui répond, le demandeur quand
  le Support lui pose une question ou lui demande de confirmer une solution. Quand un ticket **vous attend**, vous en
  êtes prévenu, et le courriel commence par **À faire** : ce qu'on attend de vous, et, s'il le faut, la date à laquelle
  le ticket sera clos sans réponse.
- Une **tâche projet** tient le client au courant : on n'attend rien de lui. Ce qui lui arrive (publication,
  modification, temps passé, message, fin) arrive dans la cloche et dans le **bilan de la semaine**, jamais par un
  courriel à chaque étape. Le Support peut tout de même **Informer par courriel**, à la main (voir plus bas). Quand
  il attend une réponse, il **pose une question** dans la tâche : elle engage, comme un ticket, les personnes à qui
  elle est posée (voir [Poser une question au client](#modal-tma.taches.questions)).
- Le Support n'apprend d'une tâche que ce que le client y écrit (un message, une note) : c'est à voir, comme un ticket
  qui l'attend.

## Les destinataires

Chaque ticket et chaque tâche a ses **destinataires**, visibles dans le bloc **Destinataires** du volet :

- les **principaux** voient tout dans la **cloche** de l'application ;
- les **secondaires** ne l'apprennent que dans leur **bilan**, en bas, sous **En copie** ;
- les autres ne reçoivent rien.

On en choisit un ou plusieurs de chaque sorte, et on les change tout au long de la vie du ticket (**Modifier**) ;
**Tout le monde** coche d'un clic toutes les personnes possibles ; sur une tâche, **Toute l'équipe projet** ajoute
l'équipe de son projet ; **Vider** retire tout (l'auteur reste principal). Une personne ajoutée aux principaux en est
prévenue dans la cloche.

### Qui devient destinataire tout seul

| Moment | Qui devient principal |
| --- | --- |
| Création d'un ticket ou d'une tâche | son auteur, qui le reste toujours (et le demandeur d'un ticket préparé par le Support) ; pour une tâche, proposée d'office : l'équipe de son projet |
| **Transmettre au Support** | qui transmet, et le Support (qui décide lui-même qui le reçoit) |
| **Transmettre au Support** (le contact) | le contact au Support choisi par le demandeur (d'office s'il n'y en a qu'un) |
| Publication d'une tâche | au moins une personne du client, à choisir (ou **Toute l'équipe projet**, **Tout le monde**) |

### Qui peut être destinataire

- Dans l'application du client : ses **correspondants du Support**, et **le Support**, nommé par le compte passerelle
  de l'application, avec certains membres du Support si l'application le permet.
- Chez le Support : ses membres, et les **correspondants** du client.

Un destinataire **principal** du côté du client peut aussi **prendre la main** quand le demandeur est absent (voir
[Prendre la main](#modal-tma.tickets.question-reponse)) ; l'ancien demandeur en est prévenu.

Chaque application prévient **ses propres utilisateurs** : quand le Support écrit sur un ticket de Desapp, c'est
Desapp qui prévient ses destinataires, à la réception.

## Ce qui part, et par où

| Ce qui arrive | Destinataire principal | Secondaire |
| --- | --- | --- |
| Un ticket **vous attend** : une demande arrive, on vous répond, une question, une solution à confirmer, une demande renvoyée, une pause qui finit, un ticket que le Support ouvre pour vous | la cloche, et un **courriel** si vous avez choisi l'**e-mail direct**, sinon votre **bilan du matin** | bilan du matin |
| La **fin** d'un ticket : clos, refusé, retiré, clos d'office ou automatiquement | la cloche et le bilan du matin | bilan du matin |
| Le reste sur un ticket : une note, une pause, une prise de main, un autre qui répond | la cloche | bilan du matin |
| Une **relance** faite à la main | un courriel, **toujours**, et la cloche | — |
| L'**avertissement** de clôture automatique | comme un ticket qui vous attend | — |
| Ce qui arrive à une **tâche** (client) | la cloche et le **bilan de la semaine** | bilan de la semaine |
| **Informer par courriel** sur une tâche | un courriel, **toujours**, et la cloche | bilan de la semaine |
| Une **question du Support** sur une tâche, à qui elle est posée (client) | comme un ticket qui vous attend | comme un ticket qui vous attend |
| Le client écrit sur une tâche (Support) | comme un ticket qui vous attend | bilan du matin |

## Mes notifications

Dans le menu du compte › **Profil**, section **Notifications du Support** (le lien **Notifications › Mes
notifications**, en tête de la liste des tickets, l'ouvre directement) :

- **E-mail direct** : un courriel dès qu'un ticket attend votre action ;
- **Bilan du matin** : ce qui vous attend, la fin de vos tickets, et ce que vous suivez en copie ;
- **Bilan de la semaine** : ce qui a avancé sur vos tâches projet.

Il faut garder l'**e-mail direct** ou le **bilan du matin**, ou les deux : un ticket qui vous attend doit vous
parvenir. La **cloche** ne se coupe pas. Tant qu'on n'a rien choisi, c'est le réglage de l'application qui s'applique
(d'origine : la cloche, le bilan du matin et celui de la semaine, sans e-mail direct). L'application peut aussi fixer
ce réglage pour tous ; **Mes notifications** n'apparaît alors pas. Sans le gestionnaire d'options utilisateur, les
valeurs de l'application s'appliquent à tout le monde, sans choix personnel.

## Les courriels

Chaque courriel porte le logo de l'application. Celui d'un **ticket** prend sa couleur principale et s'ouvre sur
**À faire** ; celui d'une **tâche**, sa couleur secondaire et **Pour information** (une question du Support, elle,
prend la couleur et l'**À faire** d'un ticket). L'objet dit ce qui se passe :
« [DES-0012] Le Support attend votre réponse – Export des dons ». En bas, **Régler mes notifications** mène à votre
profil.

## Le bilan du matin

Chaque jour ouvré à 8 h, chacun (sauf qui l'a refusé) reçoit **un seul courriel** :

- **À faire** : ses demandes qui attendent sa réponse ou sa confirmation (avec la date de clôture sans réponse), les
  demandes nouvelles sans contact, ce qu'il traite, ses pauses qui reprennent aujourd'hui ; sous chaque ticket, ce qui
  s'y est passé depuis le dernier bilan ; les **questions du Support** sur ses projets, avec leur texte. Ce qui attend
  depuis plus de 5 jours est signalé **en retard** ;
- **Nouveautés** : le reste, là où il est destinataire principal (une fin de ticket…) ;
- **En copie**, en bas : là où il est destinataire secondaire.

Personne ne reçoit de courriel vide. L'heure, les jours et le fuseau se règlent.

**Notifications › Envoyer maintenant**, en tête de la liste des tickets, envoie tout de suite leur bilan du matin à
ceux qui ont quelque chose en attente. Il faut le droit « Envoyer maintenant les notifications groupées ».

## Le bilan de la semaine

Chaque lundi à 8 h, chacun reçoit, s'il y a du nouveau et s'il ne l'a pas refusé, le bilan de ses **tâches projet**,
pour information : projet par projet, chaque tâche (son état, son échéance, le temps passé) et ce qui lui est arrivé
dans la semaine ; **En copie** en bas. Une question du Support qui vous attend encore y est signalée, en tête et sur
sa tâche. Le jour et l'heure se règlent.

## Relancer

Sur la fiche d'un ticket qui attend quelqu'un d'autre, **Relancer** écrit dans l'historique un message marqué
**Relance** (votre mot, ou « Ce ticket attend votre retour. ») et prévient tout de suite qui a la main, **par courriel
même sans e-mail direct** : le demandeur quand on traite la demande, l'intervenant quand on l'attend. Le bouton
n'apparaît pas à qui a lui-même la main, ni sur un brouillon, un ticket en pause ou terminé. Il faut le droit
« Relancer qui a la main ».

## Informer par courriel

Sur la fiche d'une tâche publiée, chez le Support, **Informer par courriel** écrit votre message dans l'historique de
la tâche (marqué **Envoyé par courriel**), et ses destinataires principaux chez le client le reçoivent par courriel.
C'est la seule façon d'écrire au client par courriel à propos d'une tâche ; ses destinataires secondaires le voient
dans leur bilan de la semaine. Il faut pouvoir écrire sur la tâche.

## Le rappel de ce qui ne bouge plus

Chaque jour ouvré à 7 h 30, juste avant le bilan, qui a la main reçoit **dans son bilan du matin** (jamais par un
courriel à part) un rappel pour :

- un ticket qui **n'a pas changé d'état depuis 7 jours** (ni brouillon, ni en pause, ni terminé) ;
- chez le Support, une **tâche en cours sans activité depuis 7 jours** (aucun message, note ou temps) : une tâche ne
  change d'état qu'à sa publication et à sa fin.

Le rappel revient tous les 7 jours tant que rien ne bouge. Le délai se règle. Un ticket qui attend le demandeur
reçoit ainsi un rappel à 7 et 14 jours, puis l'avertissement de clôture à 21 jours, et se clôt à 28. Voir
[La clôture automatique](#modal-tma.tickets.cloture-automatique).

## Ce qui prévient aussi

- **La clôture automatique** : le demandeur est averti 7 jours avant (comme un ticket qui l'attend), puis la clôture
  arrive dans sa cloche et son bilan.
- **Les seuils des forfaits** : les adresses configurées. Voir [Les forfaits](#modal-tma.forfaits).

## Les tâches automatiques

| Tâche | Quand | Ce qu'elle fait |
| --- | --- | --- |
| Clôture automatique | chaque jour à 7 h | avertit puis clôture les tickets sans réponse du demandeur |
| Rappel de ce qui ne bouge plus | jours ouvrés à 7 h 30 | rappelle à qui a la main ce qui n'a pas bougé depuis 7 jours |
| Reprise des pauses | tous les quarts d'heure | reprend les tickets en pause arrivés à leur date |
| Publications programmées | chaque minute | publie les tâches et les tickets préparés arrivés à leur date |
| Bilan du matin | jours ouvrés à 8 h | envoie le bilan des tickets |
| Bilan de la semaine | le lundi à 8 h | envoie le bilan des tâches projet |
| Ménage | chaque nuit | efface les notifications envoyées depuis plus de 90 jours |
