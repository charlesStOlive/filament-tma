<?php

namespace CharlesStOlive\FilamentTma\States\Ticket;

use CharlesStOlive\FilamentTma\Enums\Side;

class Paused extends TicketState
{
    public static $name = 'paused';

    public function getLabel(): string
    {
        return __('filament-tma::states.ticket.paused.label');
    }

    public function getColor(): string
    {
        return 'gray';
    }

    public function getIcon(): string
    {
        return 'heroicon-m-pause-circle';
    }

    public function getDescription(): ?string
    {
        return __('filament-tma::states.ticket.paused.description');
    }

    public function inHandsOf(): ?Side
    {
        return Side::Handler;
    }
}
