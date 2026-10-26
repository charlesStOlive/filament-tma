<?php

namespace CharlesStOlive\FilamentTma\States\Ticket;

use CharlesStOlive\FilamentTma\Enums\Side;

class Draft extends TicketState
{
    public static $name = 'draft';

    public function getLabel(): string
    {
        return __('filament-tma::states.task.draft.label');
    }

    public function getColor(): string
    {
        return 'gray';
    }

    public function getIcon(): string
    {
        return 'heroicon-m-pencil-square';
    }

    public function getDescription(): ?string
    {
        return __('filament-tma::states.ticket.draft.description');
    }

    public function inHandsOf(): ?Side
    {
        return Side::Requester;
    }
}
