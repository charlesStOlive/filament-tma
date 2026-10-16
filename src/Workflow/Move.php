<?php

namespace CharlesStOlive\FilamentTma\Workflow;

use CharlesStOlive\FilamentTma\Enums\Side;
use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Models\StateTransition;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\States\Ticket\TicketState;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Component;

/**
 * Un mouvement du workflow d'un ticket : « Demander des précisions », « Répondre »… Il dit d'où il part (`from()`), où il
 * mène (`to()`), quelle partie peut le faire (`sides()`), ce qu'il demande (`rules()`, `formSchema()`) et ce qu'il
 * écrit (`apply()`). `TicketWorkflow` contrôle et exécute.
 *
 * Un passage normal suit le tour (addendum §3.1) : il part d'un état où c'est le tour de sa partie, et du côté du
 * demandeur, seul le demandeur le fait (`allowsRequester()`). Aucun droit ne l'ouvre. Un forçage (`override()`), lui,
 * ne dépend que de son droit.
 */
abstract class Move
{
    /** La clé, dans l'historique : `request-answer`. */
    abstract public function key(): string;

    abstract public function label(): string;

    /** Le libellé pour un ticket donné : il peut nommer son client (« Publier chez EDM », voir `Support\Terms`). */
    public function labelFor(Ticket $ticket): string
    {
        return $this->label();
    }

    public function description(): ?string
    {
        return null;
    }

    /**
     * Un passage de la zone de réponse de la fiche (sous la chronologie), plutôt qu'un bouton d'en-tête : c'est ainsi
     * que répond la partie qu'on attend, avec son texte. La clé du texte dans les données (`answer`, `question`…) ;
     * null pour un passage qui reste un bouton d'en-tête.
     */
    public function replyField(): ?string
    {
        return null;
    }

    public function color(): string
    {
        return 'primary';
    }

    public function icon(): ?string
    {
        return null;
    }

    /** @return array<int, class-string<TicketState>> */
    abstract public function from(): array;

    /** @return class-string<TicketState> */
    abstract public function to(Ticket $ticket): string;

    /**
     * Les parties qui peuvent le faire. Vide : un mouvement du système seulement (le planificateur).
     *
     * @return array<int, Side>
     */
    abstract public function sides(): array;

    /** Une condition de plus pour une partie (un forçage permis à l'une et pas à l'autre…). */
    public function allowsSide(Side $side, Ticket $ticket): bool
    {
        return true;
    }

    /**
     * Côté demandeur, qui le fait : le demandeur lui-même. « Prendre la main » est l'exception (un autre destinataire
     * principal).
     */
    public function allowsRequester(Ticket $ticket, Actor $actor): bool
    {
        return (int) $ticket->requester_actor_id === (int) $actor->getKey();
    }

    /**
     * Un forçage : le droit qui l'ouvre (`force-close`…), à la place du tour. Null pour un passage normal.
     */
    public function override(): ?string
    {
        return null;
    }

    /** Disponible pour ce ticket, en dehors des états et des droits (le circuit du Support est ouvert…). */
    public function isAvailable(Ticket $ticket): bool
    {
        return true;
    }

    /**
     * Les règles de validation des données, contrôlées pour toute origine (interface, API, commande).
     *
     * @return array<string, mixed>
     */
    public function rules(Ticket $ticket): array
    {
        return [];
    }

    /**
     * Les champs du formulaire Filament.
     *
     * @return array<int, Component|Field>
     */
    public function formSchema(Ticket $ticket): array
    {
        return [];
    }

    /** Ce que le mouvement écrit, avant le changement d'état (dans la même transaction). */
    public function apply(Ticket $ticket, array $data, Context $context): void {}

    /**
     * Appelé dans la transaction, une fois l'état changé et l'historique écrit : une exception ici annule tout
     * (la transmission au Support s'en sert pour appeler le maître).
     */
    public function committing(Ticket $ticket, StateTransition $transition, Context $context): void {}

    /** Le motif gardé dans l'historique. */
    public function reason(array $data): ?string
    {
        return isset($data['reason']) && filled($data['reason']) ? (string) $data['reason'] : null;
    }

    public function requiresConfirmation(): bool
    {
        return false;
    }
}
