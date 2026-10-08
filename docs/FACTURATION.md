# Facturation — proposition (lot 3, à valider)

Ce document propose comment le plugin alimenterait les factures de Filbreeze (CDC §14, addendum §9). Rien n'est
codé : les questions de la fin sont à trancher d'abord.

## Ce qui existe

**Dans Filbreeze**, une facture (`App\Models\Invoice`) a des items en JSON, saisis avec un Builder
(`InvoiceResource::getItemsBuilderComponent()`). Trois blocs nous concernent déjà :

| Bloc | Champs | Usage aujourd'hui |
|---|---|---|
| `tma` | début et fin (mois), nombre de tickets, nombre de tickets facturables, heures facturables, coût horaire, total | saisi à la main |
| `tasks` | titre, description, coût unitaire, quantité, total | saisi à la main |
| `forfait` | titre, description, total | saisi à la main |

Le bloc `on_quote` montre le modèle à suivre : choisir un devis remplit le bloc, avec ce qui reste à facturer.

**Dans le plugin**, chaque saisie de temps a déjà :

- un **rattachement de facture** (`invoice_type`, `invoice_id`, `invoiced_at`) ;
- son **verrou** (CDC §10.3) : une saisie rattachée ne se modifie plus et ne se supprime plus, quel que soit l'état
  de la facture.

## Proposition

1. **Un contrat et son trait**, comme tu l'avais imaginé : `Billing\ProvidesInvoiceItems` (contrat) et
   `Billing\BillsTmaTime` (trait). Le plugin les fournit ; Filbreeze les utilise dans `InvoiceResource`.
2. **Bloc `tma` : un bouton « Remplir depuis la TMA ».** Pour la société de la facture et la période choisie, il
   remplit le bloc avec :
   - le nombre de tickets travaillés et facturables ;
   - les heures facturables **pas encore facturées** ;
   - le coût horaire (une valeur par défaut configurable, ou un champ de la société).

   Le bloc garde la liste des saisies qu'il couvre (`time_entry_ulids`).
3. **Bloc `tasks` : choisir une tâche projet.** Même principe, sur le temps non facturé de cette tâche. Une tâche
   **terminée** peut être facturée entièrement ; une tâche **en cours**, seulement en partie, si le client l'accepte
   (CDC §14.4).
4. **À l'enregistrement de la facture**, les saisies couvertes sont rattachées : elles sont alors verrouillées, et
   ne sortent plus dans « ce qui reste à facturer ».
   - Si une ligne est retirée d'une facture **brouillon**, ses saisies sont détachées.
   - Une facture **finalisée** (Qonto) ne se détache pas : il faut un avoir. C'est la règle comptable du CDC §10.3,
     à confirmer.
5. **L'état de facturation est calculé**, jamais saisi (CDC §14.2), pour un ticket comme pour une tâche :
   - « Non facturé » : aucune saisie rattachée ;
   - « Partiellement facturé » : une partie seulement ;
   - « Facturé » : tout est rattaché **et** l'objet est clôturé ou terminé (CDC §14.4) ;
   - « Non facturable » : aucune saisie facturable.

   Une colonne et un filtre l'affichent dans les listes.
6. **Le reste à facturer**, par client et par mois (CDC §14.3) : un écran du maître qui montre, pour chaque mois, le
   temps réalisé, facturé et restant, et les factures concernées.

## Questions à trancher

- **F1. Le forfait.** Facture-t-on le forfait comme un montant fixe par période (bloc `forfait`), puis seulement
  les heures **au-delà** du forfait (bloc `tma`) ? Ou toutes les heures, le forfait ne servant qu'au suivi ?
- **F2. Le coût horaire.** Une valeur unique dans la config, une valeur par société, ou par contrat (sur le
  forfait) ?
- **F3. Le détail.** Une ligne de facture par mois, ou une seule ligne pour la période ? Faut-il joindre à la facture
  le détail des tickets et des heures (annexe PDF) ?
- **F4. Le moment du verrou.** À l'enregistrement du brouillon (proposé : c'est ce que dit le CDC), ou seulement à la
  finalisation ?
- **F5. Côté client.** Que voit l'application cliente de la facturation (CDC §21.1) : rien, l'état par ticket, ou les
  numéros de facture ?
