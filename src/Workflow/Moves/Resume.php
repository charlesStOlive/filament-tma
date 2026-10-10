<?php

namespace CharlesStOlive\FilamentTma\Workflow\Moves;

use CharlesStOlive\FilamentTma\Enums\Side;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\States\Ticket\AwaitingRequester;
use CharlesStOlive\FilamentTma\States\Ticket\InProgress;
use CharlesStOlive\FilamentTma\States\Ticket\Paused;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Move;

/** L'équipe qui traite la demande reprend la main : après une pause, ou sans attendre la réponse du demandeur. */
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
        return [Paused::class, AwaitingRequester::class];
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
        $ticket->waiting_since = null;
    }
}
