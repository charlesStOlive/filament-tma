<?php

namespace CharlesStOlive\FilamentTma\Workflow\Moves;

use CharlesStOlive\FilamentTma\Enums\Side;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\States\Ticket\InProgress;
use CharlesStOlive\FilamentTma\States\Ticket\Submitted;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Move;

/** L'intervenant prend la demande : il en devient le responsable. */
class TakeCharge extends Move
{
    public function key(): string
    {
        return 'take-charge';
    }

    public function label(): string
    {
        return __('Prendre en charge');
    }

    public function icon(): ?string
    {
        return 'heroicon-m-hand-raised';
    }

    public function from(): array
    {
        return [Submitted::class];
    }

    public function to(Ticket $ticket): string
    {
        return InProgress::class;
    }

    public function sides(): array
    {
        return [Side::Handler];
    }

    public function apply(Ticket $ticket, array $data, Context $context): void
    {
        $ticket->taken_at = now();
        $ticket->assignee_actor_id = $context->actor?->getKey() ?? $ticket->assignee_actor_id;
    }
}
