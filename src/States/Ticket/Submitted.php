<?php

namespace CharlesStOlive\FilamentTma\States\Ticket;

use CharlesStOlive\FilamentTma\Enums\Side;

class Submitted extends TicketState
{
    public static $name = 'submitted';

    public function getLabel(): string
    {
        return __('Nouveau');
    }

    public function getColor(): string
    {
        return 'info';
    }

    public function getIcon(): string
    {
        return 'heroicon-m-inbox-arrow-down';
    }

    public function getDescription(): ?string
    {
        return __('Attend d’être pris en charge.');
    }

    public function inHandsOf(): ?Side
    {
        return Side::Handler;
    }
}
