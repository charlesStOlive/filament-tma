<?php

namespace CharlesStOlive\FilamentTma\States\Ticket;

use CharlesStOlive\FilamentTma\Enums\Side;

class InProgress extends TicketState
{
    public static $name = 'in_progress';

    public function getLabel(): string
    {
        return __('En cours');
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
        return __('Un intervenant traite la demande.');
    }

    public function inHandsOf(): ?Side
    {
        return Side::Handler;
    }
}
