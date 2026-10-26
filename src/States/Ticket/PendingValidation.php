<?php

namespace CharlesStOlive\FilamentTma\States\Ticket;

use CharlesStOlive\FilamentTma\Enums\Side;

class PendingValidation extends TicketState
{
    public static $name = 'pending_validation';

    public function getLabel(): string
    {
        return __('filament-tma::states.ticket.pending_validation.label');
    }

    public function getColor(): string
    {
        return 'warning';
    }

    public function getIcon(): string
    {
        return 'heroicon-m-check-badge';
    }

    public function getDescription(): ?string
    {
        return __('filament-tma::states.ticket.pending_validation.description');
    }

    public function inHandsOf(): ?Side
    {
        return Side::Requester;
    }
}
