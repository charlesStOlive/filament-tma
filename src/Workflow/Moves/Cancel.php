<?php

namespace CharlesStOlive\FilamentTma\Workflow\Moves;

use CharlesStOlive\FilamentTma\Enums\Side;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\States\Ticket\Cancelled;
use CharlesStOlive\FilamentTma\States\Ticket\Draft;
use CharlesStOlive\FilamentTma\States\Ticket\Submitted;
use CharlesStOlive\FilamentTma\Workflow\Concerns\WritesMessages;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Move;

/**
 * Le demandeur retire sa demande : un brouillon, ou une demande à laquelle le Support n'a pas encore répondu (Nouveau, la
 * seule exception au tour, addendum §3.1). Un motif est demandé, sauf pour un brouillon. Le Support, lui, refuse une
 * demande nouvelle ou force la clôture.
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
        return __('filament-tma::workflow.ticket.cancel.label');
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
        return [Draft::class, Submitted::class];
    }

    public function to(Ticket $ticket): string
    {
        return Cancelled::class;
    }

    public function sides(): array
    {
        return [Side::Requester];
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
