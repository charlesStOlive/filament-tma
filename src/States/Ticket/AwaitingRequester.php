<?php

namespace CharlesStOlive\FilamentTma\States\Ticket;

use CharlesStOlive\FilamentTma\Enums\Side;

class AwaitingRequester extends TicketState
{
    public static $name = 'awaiting_requester';

    public function getLabel(): string
    {
        return __('filament-tma::states.ticket.awaiting_requester.label');
    }

    public function getColor(): string
    {
        return 'warning';
    }

    public function getIcon(): string
    {
        return 'heroicon-m-chat-bubble-left-ellipsis';
    }

    public function getDescription(): ?string
    {
        return __('filament-tma::states.ticket.awaiting_requester.description');
    }

    public function inHandsOf(): ?Side
    {
        return Side::Requester;
    }
}
