<?php

namespace CharlesStOlive\FilamentTma\Workflow\Moves;

use CharlesStOlive\FilamentTma\Enums\Side;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\States\Ticket\InProgress;
use CharlesStOlive\FilamentTma\States\Ticket\Paused;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Move;

/**
 * Le Support reprend un ticket qu'il avait mis en pause. Reprendre la main quand c'est le tour du demandeur est un
 * forçage (`ForceReturn`).
 */
class Resume extends Move
{
    public function key(): string
    {
        return 'resume';
    }

    public function label(): string
    {
        return __('Reprendre');
    }

    public function icon(): ?string
    {
        return 'heroicon-m-play';
    }

    public function from(): array
    {
        return [Paused::class];
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
        $ticket->paused_until = null;
    }
}
