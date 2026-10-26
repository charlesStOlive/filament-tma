<?php

namespace CharlesStOlive\FilamentTma\States\Ticket;

use CharlesStOlive\FilamentTma\Enums\Side;

class Cancelled extends TicketState
{
    public static $name = 'cancelled';

    public function getLabel(): string
    {
        return __('filament-tma::states.ticket.cancelled.label');
    }

    public function getColor(): string
    {
        return 'danger';
    }

    public function getIcon(): string
    {
        return 'heroicon-m-x-circle';
    }

    public function getDescription(): ?string
    {
        return __('filament-tma::states.ticket.cancelled.description');
    }

    public function inHandsOf(): ?Side
    {
        return null;
    }

    public function isFinal(): bool
    {
        return true;
    }
}
