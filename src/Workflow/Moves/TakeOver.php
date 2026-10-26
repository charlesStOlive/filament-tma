<?php

namespace CharlesStOlive\FilamentTma\Workflow\Moves;

use CharlesStOlive\FilamentTma\Enums\RecipientRole;
use CharlesStOlive\FilamentTma\Enums\Side;
use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\States\Ticket\AwaitingRequester;
use CharlesStOlive\FilamentTma\States\Ticket\PendingValidation;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Move;

/**
 * « Prendre la main » (addendum §3.3) : quand c'est le tour du demandeur, un autre destinataire principal de son
 * application devient le demandeur, pour répondre, confirmer ou renvoyer à sa place, et pour la suite. L'état ne
 * change pas ; l'ancien demandeur reste destinataire principal, et peut reprendre la main de la même façon.
 */
class TakeOver extends Move
{
    public function key(): string
    {
        return 'take-over';
    }

    public function label(): string
    {
        return __('filament-tma::workflow.ticket.take_over.label');
    }

    public function labelFor(Ticket $ticket): string
    {
        return $ticket->requester === null ? $this->label() : __('filament-tma::workflow.ticket.take_over.label_named', ['name' => $ticket->requester->name]);
    }

    public function description(): ?string
    {
        return __('filament-tma::workflow.ticket.take_over.description');
    }

    public function color(): string
    {
        return 'gray';
    }

    public function icon(): ?string
    {
        return 'heroicon-m-hand-raised';
    }

    public function from(): array
    {
        return [AwaitingRequester::class, PendingValidation::class];
    }

    /** L'état ne change pas. */
    public function to(Ticket $ticket): string
    {
        return $ticket->state::class;
    }

    public function sides(): array
    {
        return [Side::Requester];
    }

    /** Un destinataire principal de l'application du demandeur, qui n'est pas déjà le demandeur. */
    public function allowsRequester(Ticket $ticket, Actor $actor): bool
    {
        return (int) $ticket->requester_actor_id !== (int) $actor->getKey()
            && $actor->instance_uid === $ticket->source_instance_uid
            && $ticket->recipients()->where('actor_id', $actor->getKey())->where('role', RecipientRole::Primary->value)->exists();
    }

    public function requiresConfirmation(): bool
    {
        return true;
    }

    public function reason(array $data): ?string
    {
        return null;
    }

    public function apply(Ticket $ticket, array $data, Context $context): void
    {
        $ticket->requester_actor_id = $context->actor->getKey();
    }
}
