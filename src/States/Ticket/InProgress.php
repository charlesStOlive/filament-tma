<?php

namespace CharlesStOlive\FilamentTma\States\Ticket;

use CharlesStOlive\FilamentTma\Enums\Side;

class InProgress extends TicketState
{
    public static $name = 'in_progress';

    public function getLabel(): string
    {
        return __('filament-tma::common.in_progress');
    }

    public function getColor(): string
    {
        return 'primary';
    }

    public function getIcon(): string
    {
        return 'heroicon-m-wrench-screwdriver';
    }

    public function getDescription(): ?string
    {
        return __('filament-tma::states.ticket.in_progress.description');
    }

    public function inHandsOf(): ?Side
    {
        return Side::Handler;
    }
}
