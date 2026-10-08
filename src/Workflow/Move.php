<?php

namespace CharlesStOlive\FilamentTma\Workflow;

use CharlesStOlive\FilamentTma\Enums\Side;
use CharlesStOlive\FilamentTma\Models\StateTransition;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\States\Ticket\TicketState;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Component;

/**
 * Un mouvement du workflow d'un ticket : « Prendre en charge », « Répondre »… Il dit d'où il part (`from()`), où il
 * mène (`to()`), quelle partie peut le faire (`sides()`), ce qu'il demande (`rules()`, `formSchema()`) et ce qu'il
 * écrit (`apply()`). `TicketWorkflow` contrôle et exécute ; le droit de chaque utilisateur est la permission
 * `tma.ticket.workflow.{clé}` (voir `Support\TmaGate`).
 */
abstract class Move
{
    /** La clé, dans l'historique et dans la permission : `take-charge`. */
    abstract public function key(): string;

    abstract public function label(): string;

    public function description(): ?string
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

    /** Une condition de plus pour une partie (l'annulation par le demandeur ne vaut qu'avant la prise en charge…). */
    public function allowsSide(Side $side, Ticket $ticket): bool
    {
        return true;
    }

    /** Disponible pour ce ticket, en dehors des états et des droits (le circuit centre de service est ouvert…). */
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
     * (la transmission au centre de service s'en sert pour appeler le maître).
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
