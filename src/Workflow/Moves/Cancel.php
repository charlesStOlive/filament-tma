<?php

namespace CharlesStOlive\FilamentTma\Workflow\Moves;

use CharlesStOlive\FilamentTma\Enums\Side;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\States\Ticket\AwaitingRequester;
use CharlesStOlive\FilamentTma\States\Ticket\Cancelled;
use CharlesStOlive\FilamentTma\States\Ticket\Draft;
use CharlesStOlive\FilamentTma\States\Ticket\InProgress;
use CharlesStOlive\FilamentTma\States\Ticket\Paused;
use CharlesStOlive\FilamentTma\States\Ticket\PendingValidation;
use CharlesStOlive\FilamentTma\States\Ticket\Submitted;
use CharlesStOlive\FilamentTma\Workflow\Concerns\WritesMessages;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Move;

/**
 * Abandonner la demande. Le demandeur le peut tant que personne ne l'a prise en charge (brouillon, soumise) ;
 * l'intervenant, à tout moment avant la fin. Un motif est demandé, sauf pour un brouillon.
 */
class Cancel extends Move
{
    use WritesMessages;

    public function key(): string
    {
        return 'cancel';
    }

    public function label(): string
    {
        return __('Annuler');
    }

    public function color(): string
    {
        return 'danger';
    }

    public function icon(): ?string
    {
        return 'heroicon-m-x-mark';
    }

    public function from(): array
    {
        return [Draft::class, Submitted::class, InProgress::class, AwaitingRequester::class, Paused::class, PendingValidation::class];
    }

    public function to(Ticket $ticket): string
    {
        return Cancelled::class;
    }

    public function sides(): array
    {
        return [Side::Requester, Side::Handler];
    }

    public function allowsSide(Side $side, Ticket $ticket): bool
    {
        return match ($side) {
            Side::Requester => $ticket->state instanceof Draft || $ticket->state instanceof Submitted,
            // Un brouillon appartient à celui qui l'écrit.
            Side::Handler => ! $ticket->state instanceof Draft,
        };
    }

    public function rules(Ticket $ticket): array
    {
        return ['reason' => [$ticket->state instanceof Draft ? 'nullable' : 'required', 'string', 'max:2000']];
    }

    public function formSchema(Ticket $ticket): array
    {
        return [$this->reasonField(! $ticket->state instanceof Draft)];
    }

    public function requiresConfirmation(): bool
    {
        return true;
    }

    public function apply(Ticket $ticket, array $data, Context $context): void
    {
        $ticket->cancelled_at = now();
    }
}
