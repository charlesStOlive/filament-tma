# Cahier des charges — Plugin de suivi TMA et de tâches projet

Version de travail 0.1

> Document d'origine, conservé tel quel. Les décisions prises depuis le complètent ou le
> remplacent : voir [ADDENDUM.md](ADDENDUM.md), qui prévaut en cas de divergence.

## 1. Objet du projet

Le projet consiste à concevoir et développer un plugin Laravel compatible avec Filament v5 permettant :

- le suivi des demandes de TMA ;
- le suivi des tâches de projet ;
- la saisie et la ventilation du temps passé ;
- le suivi des forfaits de TMA ;
- la préparation des éléments de facturation ;
- la communication entre une application maître et plusieurs applications clientes ;
- l’utilisation du système depuis Filament et, pour certaines opérations, depuis une commande externe.

Le plugin devra pouvoir être installé dans deux modes différents :

- **mode maître**, utilisé dans Filbreeze ;
- **mode client**, utilisé notamment dans Desapp.

Le mode de fonctionnement sera déterminé par la configuration de chaque installation.

---

## 2. Architecture générale

### 2.1 Application maître

Filbreeze sera l’application maître.

Elle centralisera les données provenant des différentes applications clientes et permettra notamment :

- de consulter les demandes de tous les clients ;
- d’accepter, qualifier et traiter les demandes de TMA ;
- d’ajouter des actions, commentaires, notes et temps passés ;
- de créer les tâches de projet ;
- de transmettre les tâches de projet aux applications clientes ;
- de suivre les forfaits et les dépassements ;
- de préparer les éléments de facturation ;
- de relier ultérieurement ces éléments au CRM et au système de facturation ;
- de piloter la synchronisation avec les applications clientes.

L’application maître fera toujours autorité en cas de divergence entre une donnée du maître et sa copie présente dans une application cliente.

### 2.2 Applications clientes

Une application cliente permettra principalement :

- de créer une demande locale ;
- de transmettre une demande au centre de service ;
- d’échanger avec les utilisateurs autorisés ;
- de consulter les demandes transmises et leur avancement ;
- de consulter les tâches projet mises à disposition par le maître ;
- de consulter les temps, forfaits et informations de facturation autorisés.

Une application cliente ne pourra jamais communiquer directement avec une autre application cliente.

### 2.3 Topologie des échanges

Les communications autorisées seront exclusivement les suivantes :

- application cliente vers application maître ;
- application maître vers application cliente.

Les communications suivantes seront interdites :

- application cliente vers une autre application cliente ;
- accès d’une application cliente aux données appartenant à une autre application ;
- modification par le client des données réservées au maître.

Les échanges seront réalisés par des API authentifiées et versionnées.

---

## 3. Terminologie retenue

Afin d’éviter les expressions ambiguës « demande interne » et « demande externe », les termes suivants seront employés.

### 3.1 Traitement local

Une demande en **traitement local** reste dans son application d’origine. Elle peut être affectée à un autre utilisateur de cette même application, mais elle n’est pas transmise à Filbreeze.

### 3.2 Prise en charge par le centre de service

Une demande peut être envoyée au maître au moyen d’une transition intitulée, par exemple :

**« Transmettre au centre de service »**

Le « centre de service » représente fonctionnellement l’équipe travaillant dans l’application maître.

Cette transition :

- change le mode de traitement de la demande ;
- déclenche sa transmission par API ;
- crée ou met à jour sa représentation dans l’application maître ;
- rend ensuite le maître prioritaire sur les données synchronisées.

### 3.3 Client et application source

Le mot **client** désigne l’organisation cliente.

L’**application source** désigne l’instance depuis laquelle la demande a été créée.

Un même client peut posséder plusieurs applications clientes, par exemple EDM-C et EDM-D.

Un ticket de TMA appartient obligatoirement :

- à un seul client ;
- à une seule application source.

Même lorsqu’un client possède plusieurs applications, un ticket ne pourra pas être rattaché simultanément à plusieurs applications.

---

## 4. Périmètre fonctionnel

Le plugin devra couvrir les fonctions suivantes :

1. gestion des demandes de TMA ;
2. gestion des tâches projet ;
3. gestion des actions et de la conversation ;
4. gestion des notes internes ;
5. saisie et ventilation du temps ;
6. suivi des forfaits et des seuils ;
7. rappels et notifications ;
8. préparation de la facturation ;
9. gestion des pièces jointes ;
10. synchronisation par API ;
11. commandes d’intégration ;
12. journalisation et traçabilité ;
13. archivage, suppression et nettoyage.

La génération comptable définitive des factures et l’intégration complète au CRM ne font pas nécessairement partie de la première version. Le plugin devra toutefois prévoir les interfaces et points d’extension nécessaires.

---

## 5. Principes de modélisation

Les tickets de TMA et les tâches projet seront des objets métier distincts. Ils auront leurs propres statuts, transitions, vocabulaire et règles métier.

En revanche, ils pourront utiliser des composants techniques communs pour :

- les actions ;
- les commentaires ;
- les notes ;
- les pièces jointes ;
- les saisies de temps ;
- l’historique ;
- les rattachements de facturation ;
- les notifications ;
- la synchronisation.

Cette solution évite de créer un modèle métier unique trop générique tout en limitant la duplication de code.

Les informations principales ne devront pas être regroupées dans une seule colonne JSON. Une structure relationnelle devra être privilégiée afin de permettre les recherches, les filtres, les agrégations mensuelles et la facturation.

Le JSON pourra être employé pour :

- des métadonnées facultatives ;
- des paramètres propres à un type d’action ;
- une copie technique d’un message API ;
- des données dont la structure est réellement variable.

---

## 6. Modèle fonctionnel indicatif

Le modèle devra prévoir au minimum les notions suivantes.

### 6.1 Client

Représente l’organisation cliente.

Il pourra être relié ultérieurement à une entité provenant du CRM.

### 6.2 Instance d’application

Représente une installation autorisée du plugin.

Elle comportera notamment :

- un identifiant global ;
- un nom ;
- un client propriétaire ;
- un type maître ou client ;
- une URL ;
- un statut d’activation ;
- les informations nécessaires à l’authentification API ;
- la date de dernière communication réussie.

### 6.3 Ticket de TMA

Un ticket comportera notamment :

- un identifiant global ;
- un numéro lisible ;
- un titre ;
- une description riche ;
- un client unique ;
- une application source unique ;
- un demandeur ;
- un responsable éventuel ;
- un mode de traitement ;
- un état métier ;
- une priorité ;
- des dates de création, échéance, résolution et clôture ;
- les informations d’attente ou de reprise ;
- un état de synchronisation ;
- un état de facturation calculé ;
- un total de temps calculé.

### 6.4 Tâche projet

Une tâche projet comportera notamment :

- un identifiant global ;
- un titre ;
- une description riche ;
- un client ;
- une application destinataire ;
- un projet éventuel ;
- une période de réalisation ;
- un état métier ;
- une date de mise à disposition ;
- une date de fin ;
- un état de facturation calculé ;
- un total de temps calculé.

### 6.5 Action ou entrée de conversation

Une action représentera un élément daté dans la chronologie d’un ticket ou d’une tâche.

Les types possibles comprendront notamment :

- commentaire ;
- note interne ;
- complément d’information ;
- demande d’information ;
- réponse ;
- événement système ;
- résumé d’intervention.

Une action pourra être ajoutée sans modifier l’état du ticket.

### 6.6 Saisie de temps

Chaque saisie de temps sera une ligne indépendante comportant notamment :

- l’objet concerné ;
- l’utilisateur ou l’intervenant ;
- la date de réalisation ;
- la durée ;
- une description ;
- le mois d’imputation ;
- son caractère facturable ou non ;
- son éventuel rattachement à une facture ;
- son origine : interface, API ou commande.

Les durées devront être enregistrées dans une unité neutre et précise, de préférence en minutes.

### 6.7 Pièce jointe

Une pièce jointe pourra être associée à :

- un ticket ;
- une tâche projet ;
- une action ;
- un commentaire ;
- une saisie de temps, si nécessaire.

### 6.8 Historique des transitions

Chaque changement d’état devra enregistrer :

- l’ancien état ;
- le nouvel état ;
- l’auteur ;
- la date ;
- l’application d’origine ;
- un motif éventuel ;
- les métadonnées techniques utiles.

### 6.9 Affectation de facturation

Une affectation de facturation permettra de relier une saisie de temps ou un montant à une facture ou à une ligne de facture.

Elle devra permettre de retrouver précisément :

- ce qui a été facturé ;
- sur quelle facture ;
- pour quel mois ;
- en quelle quantité ;
- ce qui reste à facturer.

---

## 7. Workflow des tickets de TMA

Le workflow sera piloté par Filament State Fusion ou par le composant de workflow retenu après validation technique.

### 7.1 Dimensions distinctes

Il faudra distinguer trois notions.

#### État métier

Il décrit l’avancement réel du ticket.

#### Mode de traitement

Il indique si la demande est traitée localement ou prise en charge par le centre de service.

#### État de synchronisation

Il indique si les données sont uniquement locales, en attente de transmission, synchronisées ou en erreur.

Ces trois notions ne devront pas être mélangées dans une unique liste de statuts.

### 7.2 États métier proposés

Le workflow de référence pourra comporter :

- **Brouillon** : demande en préparation ;
- **Soumise** : demande validée et envoyée à son circuit de traitement ;
- **À qualifier** : demande reçue, mais pas encore complètement analysée ;
- **En cours** : traitement actif ;
- **En attente de réponse** : une réponse est attendue d’un acteur identifié ;
- **En pause planifiée** : traitement suspendu jusqu’à une date définie ;
- **À vérifier** : le résultat doit être contrôlé ou validé ;
- **Résolue** : le traitement est terminé techniquement ;
- **Clôturée** : la demande est terminée administrativement ;
- **Annulée** : demande abandonnée ;
- **Refusée** : demande non prise en charge avec justification.

La liste exacte et les transitions autorisées devront être configurables ou extensibles.

### 7.3 Demande locale

Un brouillon reste local tant qu’aucune transition de transmission n’est exécutée.

Une demande locale pourra être soumise pour traitement à un autre utilisateur autorisé de la même application.

Tant qu’elle reste locale :

- elle ne sera pas transmise au maître ;
- aucun identifiant maître ne sera nécessaire ;
- elle restera administrée par l’application cliente.

### 7.4 Transmission au centre de service

La transition « Transmettre au centre de service » devra :

1. vérifier les droits de l’utilisateur ;
2. vérifier que le client et l’application source sont définis ;
3. valider les données obligatoires ;
4. créer ou mettre à jour le ticket dans le maître ;
5. enregistrer les identifiants de correspondance ;
6. journaliser la transmission ;
7. confirmer la bonne réception au client.

Après cette transmission, le maître devient la source d’autorité du ticket synchronisé.

### 7.5 Attente de réponse

Lors du passage en attente, le système devra enregistrer :

- l’acteur ou le groupe dont la réponse est attendue ;
- la date de début de l’attente ;
- la prochaine date de relance ;
- une date limite facultative ;
- les règles de rappel applicables.

Un utilisateur autorisé pourra ajouter une note, un commentaire ou un complément d’information pendant cette attente sans changer le statut.

L’ajout d’un commentaire ne devra pas automatiquement signifier que la réponse attendue a été reçue. Le changement d’état devra résulter d’une action ou d’une transition explicite.

### 7.6 Pause planifiée

Une demande pourra être placée en pause jusqu’à une date donnée.

À cette date, le système devra pouvoir :

- déclencher un événement ;
- envoyer une notification ;
- remettre la demande dans une file de traitement ;
- proposer ou exécuter une transition automatique selon la configuration.

---

## 8. Workflow des tâches projet

Les tâches projet seront créées exclusivement depuis l’application maître.

Le workflow minimal recommandé est le suivant :

- **Brouillon** : visible uniquement dans le maître et non transmis au client ;
- **En cours** : tâche mise à disposition du client et synchronisée ;
- **Terminée** : tâche achevée et susceptible d’être intégralement facturée ;
- **Annulée** : état facultatif permettant de conserver l’historique d’une tâche abandonnée.

Le terme « publié » ne sera pas utilisé comme état métier principal. La publication correspondra à l’action de passer la tâche de « Brouillon » à « En cours » et de la transmettre à l’application cliente.

Une tâche projet devra appartenir à un seul client et être destinée à une application cliente déterminée.

---

## 9. Actions, commentaires et notes

### 9.1 Chronologie unifiée

Chaque ticket et chaque tâche projet présenteront une chronologie regroupant :

- les commentaires ;
- les notes ;
- les demandes et réponses ;
- les changements d’état ;
- les temps saisis ;
- les ajouts ou suppressions de fichiers ;
- les événements de synchronisation ;
- les événements de facturation autorisés.

### 9.2 Commentaires

Un commentaire est destiné à participer à l’échange fonctionnel entre les acteurs autorisés.

Sa visibilité dépendra des droits de l’auteur et du profil de visibilité choisi.

### 9.3 Notes internes

Une note interne pourra être réservée :

- aux utilisateurs du maître ;
- aux utilisateurs locaux de l’application source ;
- à un groupe ou rôle précis.

Les droits détermineront qui peut :

- ajouter une note ;
- la consulter ;
- la modifier ;
- la supprimer ;
- la transmettre ou non à l’autre application.

### 9.4 Absence de transition automatique

L’ajout d’une note ou d’un commentaire ne changera pas automatiquement l’état de l’objet.

Une transition pourra éventuellement être proposée à l’utilisateur, mais elle devra rester explicite sauf règle de workflow clairement configurée.

---

## 10. Gestion du temps

### 10.1 Unité de stockage

Les durées seront enregistrées en minutes.

Elles pourront être saisies et affichées :

- en minutes ;
- en heures ;
- en journées.

La durée d’une journée sera configurable. La valeur par défaut sera :

**1 journée = 7 heures = 420 minutes**

### 10.2 Ventilation temporelle

Chaque saisie possédera sa propre date de réalisation.

Cette règle permettra de calculer :

- le temps total d’un ticket ;
- le temps total d’une tâche projet ;
- le temps par jour ;
- le temps par mois ;
- le temps par trimestre ;
- le temps par utilisateur ;
- le temps par client ;
- le temps par application source ;
- le temps facturé et restant à facturer.

Un ticket ouvert entre janvier et mars pourra ainsi comporter des interventions imputées séparément sur chacun de ces mois.

### 10.3 Modification d’une saisie

Une saisie de temps pourra être modifiée ou supprimée uniquement lorsqu’elle n’est reliée à aucune facture.

Dès qu’une saisie est rattachée à une facture, quel que soit l’état de cette facture :

- sa modification est interdite ;
- sa suppression est interdite ;
- le client ne peut pas lever ce verrou ;
- seul un utilisateur maître autorisé peut intervenir sur le rattachement ou la facture.

Pour modifier la saisie, il faudra d’abord supprimer ou annuler le rattachement de facturation selon les règles du système de facturation.

Lorsqu’une facture a une valeur comptable ou légale, une annulation ou un avoir pourra être nécessaire à la place d’une suppression physique. Cette règle sera précisée lors de l’intégration au CRM.

---

## 11. Forfaits de TMA et seuils

### 11.1 Allocation

Un volume de TMA pourra être alloué pour une période.

L’allocation pourra être définie :

- par client ;
- par application ;
- par contrat ;
- mensuellement ;
- trimestriellement.

Lorsqu’un client possède plusieurs applications, la configuration devra indiquer si le forfait est :

- partagé entre ses applications ;
- ou défini séparément pour chaque application.

### 11.2 Calcul de consommation

La consommation sera calculée à partir des saisies de temps de TMA éligibles.

Le système devra afficher :

- le volume alloué ;
- le volume consommé ;
- le volume restant ;
- le pourcentage consommé ;
- le dépassement éventuel ;
- la répartition mensuelle.

### 11.3 Seuils configurables

Plusieurs seuils pourront être configurés, par exemple :

- 80 % ;
- 90 % ;
- 100 % ;
- 110 %.

Chaque franchissement de seuil déclenchera un événement métier.

Exemples d’événements :

- seuil de consommation atteint ;
- forfait entièrement consommé ;
- forfait dépassé.

Un événement ne devra être déclenché qu’une seule fois par franchissement de seuil et par période, sauf réinitialisation explicite.

### 11.4 Réactions aux événements

Les événements pourront être écoutés par l’application afin de :

- afficher une alerte ;
- envoyer une notification ;
- envoyer un courriel ;
- demander une validation ;
- bloquer ou non certaines actions ;
- créer une tâche de suivi ;
- appeler ultérieurement un service tiers.

Le dépassement ne bloquera pas obligatoirement la saisie de temps. Le comportement sera configurable.

---

## 12. Rappels et notifications

### 12.1 Récapitulatif quotidien

Par défaut, les rappels relatifs aux demandes en attente seront regroupés dans un courriel récapitulatif quotidien.

La configuration devra prévoir :

- l’heure d’envoi ;
- le fuseau horaire ;
- les destinataires ;
- les types de demandes inclus ;
- les jours d’envoi ;
- les seuils de retard ;
- l’activation ou non du récapitulatif.

### 12.2 Notifications immédiates

Une notification immédiate pourra être envoyée lorsque la configuration ou une action explicite le demande.

Une notification immédiate ne devra pas entraîner l’envoi supplémentaire du même rappel dans le récapitulatif, sauf configuration contraire.

### 12.3 Événements notifiables

Les notifications pourront notamment concerner :

- une nouvelle demande transmise ;
- une affectation ;
- une demande de réponse ;
- un commentaire visible ajouté ;
- une date de reprise atteinte ;
- un seuil de forfait atteint ;
- un dépassement ;
- un changement important de statut ;
- une erreur de synchronisation.

---

## 13. Droits et autorisations

Le système devra s’appuyer sur le mécanisme de permissions retenu par l’environnement Filament, notamment Permissions Sync si sa compatibilité est confirmée.

Les droits devront pouvoir être définis :

- par ressource ;
- par action ;
- par rôle ;
- par état métier ;
- par transition ;
- par type d’application ;
- par niveau de visibilité.

Les autorisations à prévoir comprendront notamment :

- voir la liste ;
- voir un élément ;
- créer ;
- modifier ;
- supprimer ;
- soumettre localement ;
- transmettre au centre de service ;
- exécuter une transition donnée ;
- affecter un responsable ;
- ajouter un commentaire ;
- ajouter une note interne ;
- voir les notes internes ;
- joindre un fichier ;
- saisir du temps ;
- modifier du temps ;
- consulter les informations de facturation ;
- gérer les factures ;
- relancer une synchronisation ;
- exécuter un nettoyage.

Les contrôles devront être appliqués côté serveur. Le masquage d’un bouton dans Filament ne constituera jamais à lui seul un contrôle de sécurité.

---

## 14. Facturation

### 14.1 Cycles

Un client pourra avoir un cycle de facturation :

- mensuel ;
- trimestriel.

Les tableaux, filtres, agrégats et exports devront tenir compte de ce cycle.

### 14.2 États de facturation

L’état de facturation sera indépendant de l’état métier.

Les valeurs attendues seront :

- **Non facturé** ;
- **Partiellement facturé** ;
- **Facturé** ;
- éventuellement **Non facturable**.

Cet état devra de préférence être calculé à partir des affectations de facturation plutôt que modifié manuellement.

### 14.3 Facturation partielle

Un ticket ou une tâche pourra être facturé partiellement.

Le système devra permettre d’identifier, pour chaque mois :

- la durée réalisée ;
- la durée facturée ;
- la durée restant à facturer ;
- la ou les factures concernées.

### 14.4 Facturation complète

Un ticket de TMA ou une tâche projet ne pourra être considéré comme intégralement facturé que s’il est terminé selon son workflow.

Une facturation partielle pourra intervenir avant la fin si les règles du client l’autorisent.

### 14.5 Passerelle CRM

Le plugin devra prévoir des contrats ou interfaces permettant de fournir ultérieurement :

- la résolution d’un client CRM ;
- la création d’une facture ;
- la création d’une ligne de facture ;
- la consultation d’une facture ;
- l’annulation d’une facture ou d’un rattachement ;
- la récupération de l’état de facturation.

Les classes concrètes utilisées devront pouvoir être déclarées dans la configuration.

---

## 15. API et synchronisation

### 15.1 Principes

Les API devront être :

- authentifiées ;
- chiffrées via HTTPS ;
- versionnées ;
- limitées par permissions ;
- journalisées ;
- idempotentes pour les opérations de création ;
- protégées contre l’accès aux données d’un autre client.

### 15.2 Identification

Chaque objet synchronisé devra disposer :

- d’un identifiant global stable, de type UUID ou ULID ;
- de son identifiant local ;
- de l’identifiant de l’application source ;
- de son identifiant dans le maître ;
- d’un numéro de version ou d’une date de dernière modification.

### 15.3 API exposée par le maître

Le maître devra permettre, selon les droits :

- la transmission d’un ticket ;
- l’ajout d’une action autorisée ;
- l’ajout d’un commentaire ;
- l’ajout d’une pièce jointe ;
- l’ajout d’une saisie de temps autorisée ;
- la consultation d’un ticket synchronisé ;
- la consultation des tâches projet destinées à l’application ;
- la consultation des données de forfait autorisées ;
- la récupération des changements depuis une date ou une version.

### 15.4 API exposée par le client

Le client devra pouvoir recevoir du maître :

- la création ou mise à disposition d’une tâche projet ;
- la mise à jour d’un ticket ;
- un changement d’état ;
- une action ou un commentaire ;
- une information de forfait ;
- une information de facturation autorisée ;
- une demande d’actualisation.

### 15.5 Autorité du maître

Après transmission d’un ticket, les champs définis comme pilotés par le maître ne pourront pas être écrasés par une modification cliente.

Les champs éventuellement modifiables côté client devront être explicitement listés.

En cas de conflit :

1. la version du maître est conservée ;
2. le conflit est journalisé ;
3. l’application cliente est réalignée ;
4. une alerte peut être déclenchée.

### 15.6 Fiabilité

Même en l’absence de mode hors connexion, les erreurs réseau temporaires devront être gérées.

Le système devra prévoir :

- des tentatives de renvoi ;
- un état « synchronisation en erreur » ;
- un journal technique ;
- une relance manuelle ;
- une protection contre les créations en double ;
- une alerte après plusieurs échecs.

---

## 16. Commandes et intégrations externes

Une ou plusieurs commandes devront permettre d’interagir avec les tâches depuis un environnement de développement.

Les fonctions attendues sont notamment :

- lister les tâches disponibles ;
- filtrer par client, application, projet ou état ;
- afficher une tâche précise ;
- sélectionner une tâche par identifiant ;
- ajouter une saisie de temps ;
- ajouter un résumé d’intervention ;
- éventuellement créer une nouvelle tâche projet si l’utilisateur possède les droits nécessaires.

Exemple fonctionnel :

> Ajouter deux heures à la tâche sélectionnée et enregistrer un résumé de 200 mots décrivant le travail réalisé.

La commande devra :

1. identifier l’application et le projet concernés ;
2. authentifier l’utilisateur ou l’intégration ;
3. contrôler les permissions ;
4. enregistrer séparément la durée et le résumé ;
5. retourner un accusé d’enregistrement ;
6. synchroniser l’information avec le maître.

Aucun mécanisme hors connexion n’est demandé.

---

## 17. Contenus riches et pièces jointes

### 17.1 Éditeur

Les descriptions et commentaires pourront utiliser un éditeur de texte riche compatible avec Filament.

L’intégration de fichiers pourra utiliser FilePond ou un composant équivalent.

### 17.2 Sécurité HTML

Tout contenu HTML devra être désinfecté côté serveur.

Le système devra interdire notamment :

- les balises de script ;
- les attributs d’événements JavaScript ;
- les URLs dangereuses ;
- les iframes non autorisées ;
- les styles ou contenus permettant une injection ;
- les SVG non sécurisés.

L’affichage ne devra jamais reposer uniquement sur un nettoyage effectué dans le navigateur.

### 17.3 Sécurité des fichiers

Le système devra prévoir :

- une liste blanche des extensions et types MIME ;
- une taille maximale configurable ;
- une vérification du contenu réel du fichier ;
- des noms de stockage générés ;
- un stockage non exécutable ;
- un contrôle d’autorisation à chaque téléchargement ;
- une analyse antivirus lorsque l’infrastructure le permet ;
- une interdiction par défaut des fichiers actifs ou exécutables.

Les images insérées dans le contenu devront suivre les mêmes règles que les autres pièces jointes.

---

## 18. Suppression, archivage et nettoyage

Le plugin devra fournir des outils de nettoyage capables de supprimer les fichiers devenus inutiles.

Ces outils devront couvrir :

- les pièces jointes liées à un ticket supprimé ;
- les fichiers intégrés dans un contenu riche ;
- les pièces jointes liées à une tâche projet supprimée ;
- les fichiers temporaires abandonnés ;
- les fichiers orphelins ;
- les copies synchronisées devenues obsolètes.

Le nettoyage devra proposer :

- un mode de simulation ;
- une période de rétention configurable ;
- une journalisation ;
- un rapport du nombre de fichiers concernés ;
- une vérification des rattachements avant suppression ;
- une protection des éléments liés à une facture ou soumis à une obligation de conservation.

La suppression métier pourra commencer par une suppression logique. La destruction physique des fichiers pourra intervenir après le délai de rétention.

Une suppression initiée par le maître devra pouvoir être répercutée sur le client lorsque cela est autorisé.

---

## 19. Configuration du plugin

La configuration devra permettre de définir au minimum :

- le mode `master` ou `client` ;
- l’identifiant de l’instance ;
- l’URL du maître ;
- les informations d’authentification API ;
- la durée d’une journée de travail ;
- l’unité d’affichage par défaut ;
- le cycle de facturation ;
- le volume de TMA alloué ;
- les seuils d’alerte ;
- le comportement en cas de dépassement ;
- l’heure du récapitulatif quotidien ;
- les types et tailles de fichiers autorisés ;
- les règles de rétention ;
- les classes de passerelle CRM ;
- les classes de résolution des clients et utilisateurs ;
- les services de stockage et de notification ;
- les règles de synchronisation.

Les secrets API ne devront pas être conservés en clair dans une configuration versionnée.

---

## 20. Dépendances et intégration Filament

Le plugin ciblera Laravel avec Filament v5.

Les dépendances envisagées comprennent :

- le package de gestion des états et transitions Filament State Fusion ;
- le mécanisme de synchronisation des permissions ;
- les composants d’interface Filament nécessaires ;
- Prism si son utilisation est justifiée pour l’affichage de contenu technique ;
- FilePond ou une intégration équivalente pour les fichiers.

Les noms Composer exacts, versions et compatibilités devront être vérifiés dans Filbreeze et Desapp avant l’implémentation.

Le plugin devra publier ou fournir proprement :

- ses migrations ;
- sa configuration ;
- ses traductions ;
- ses policies et permissions ;
- ses ressources Filament ;
- ses commandes ;
- ses routes API ;
- ses événements et contrats d’intégration.

---

## 21. Interfaces Filament

### 21.1 Dans l’application cliente

L’utilisateur autorisé devra pouvoir consulter :

- ses demandes locales ;
- ses demandes transmises ;
- leur état ;
- la chronologie visible ;
- les temps visibles ;
- les tâches projet mises à disposition ;
- la consommation du forfait ;
- les informations de facturation autorisées.

Il pourra, selon ses droits :

- créer une demande ;
- la soumettre localement ;
- la transmettre au centre de service ;
- ajouter un commentaire ;
- ajouter une pièce jointe ;
- répondre à une demande d’information.

### 21.2 Dans l’application maître

L’utilisateur autorisé devra pouvoir :

- filtrer par client et application source ;
- consulter l’ensemble des tickets transmis ;
- créer et publier des tâches projet ;
- modifier les données maîtrisées ;
- exécuter les transitions ;
- ajouter du temps ;
- consulter les consommations ;
- suivre les seuils ;
- préparer la facturation ;
- consulter les erreurs de synchronisation.

### 21.3 Filtres

Les listes devront pouvoir être filtrées par :

- client ;
- application source ;
- type d’objet ;
- état métier ;
- mode de traitement ;
- responsable ;
- période ;
- mois ;
- trimestre ;
- cycle de facturation ;
- état de facturation ;
- dépassement de forfait ;
- état de synchronisation.

---

## 22. Événements métier

Le plugin devra publier des événements permettant aux applications hôtes d’ajouter leurs propres réactions.

Les événements à prévoir comprennent notamment :

- ticket créé ;
- ticket soumis localement ;
- ticket transmis au centre de service ;
- ticket synchronisé ;
- synchronisation échouée ;
- transition exécutée ;
- commentaire ajouté ;
- note ajoutée ;
- temps ajouté ;
- temps modifié ;
- seuil de consommation atteint ;
- forfait dépassé ;
- rappel arrivé à échéance ;
- tâche projet mise à disposition ;
- tâche projet terminée ;
- état de facturation modifié ;
- nettoyage terminé.

Les réactions à ces événements seront découplées du cœur du plugin.

---

## 23. Traçabilité

Les actions sensibles devront être auditées.

L’audit devra enregistrer au minimum :

- l’auteur ;
- l’application ;
- la date ;
- l’action ;
- l’objet concerné ;
- l’ancienne valeur ;
- la nouvelle valeur ;
- l’origine : interface, API, commande ou tâche planifiée.

Les modifications de temps, transitions, permissions, synchronisations et opérations de facturation devront toujours être traçables.

---

## 24. Exigences non fonctionnelles

Le système devra :

- fonctionner avec Laravel et Filament v5 ;
- respecter les conventions des applications hôtes ;
- être installable en tant que plugin Composer ;
- garantir l’isolation des clients et applications ;
- utiliser les files d’attente pour les opérations lentes ;
- utiliser le planificateur Laravel pour les rappels ;
- conserver les dates techniques en UTC ;
- afficher les dates dans le fuseau configuré ;
- être entièrement traduisible ;
- fournir des messages d’erreur compréhensibles ;
- éviter les doubles traitements ;
- supporter des volumes importants de saisies de temps ;
- disposer de tests automatisés fonctionnels et de sécurité.

---

## 25. Critères d’acceptation principaux

Le système sera considéré conforme lorsque les scénarios suivants seront validés :

1. un ticket appartient à un seul client et à une seule application source ;
2. une demande locale n’est jamais transmise sans transition explicite ;
3. une application cliente ne peut accéder à aucune autre application cliente ;
4. une transmission crée une correspondance fiable avec le maître ;
5. après transmission, le maître reste la source d’autorité ;
6. un commentaire peut être ajouté pendant une attente sans changer l’état ;
7. les notes internes respectent les permissions de visibilité ;
8. les temps sont ventilés correctement par mois ;
9. la conversion en journées utilise la durée configurable ;
10. les seuils de consommation déclenchent les événements attendus ;
11. une saisie liée à une facture ne peut plus être modifiée ;
12. une tâche projet en brouillon n’est pas visible côté client ;
13. le passage d’une tâche projet en cours la transmet au client ;
14. un ticket ou une tâche non terminé ne peut pas être marqué intégralement facturé ;
15. les appels API sont authentifiés, idempotents et isolés par application ;
16. les contenus HTML dangereux sont neutralisés ;
17. les fichiers non autorisés sont refusés ;
18. le nettoyage supprime les fichiers orphelins sans toucher aux fichiers protégés ;
19. chaque action sensible apparaît dans l’audit ;
20. les droits sont contrôlés côté serveur et peuvent dépendre de l’état.

---

## 26. Points à confirmer lors de l’étude technique

Les décisions suivantes ne bloquent pas la conception fonctionnelle, mais devront être confirmées avant le développement :

- noms et versions exactes des packages Composer ;
- structure actuelle des modèles User, Client, CRM et Facture ;
- méthode d’authentification entre les applications ;
- partage ou séparation du forfait entre plusieurs applications d’un même client ;
- règles déterminant quelles saisies consomment le forfait ;
- transitions exactes de chaque workflow ;
- durée de conservation des fichiers et journaux ;
- règles comptables d’annulation des factures ;
- droits précis accordés aux différents rôles ;
- données de facturation pouvant être affichées dans une application cliente.

Ces éléments devront être résolus par la configuration lorsque les besoins peuvent varier selon le client.
