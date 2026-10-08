<?php

namespace CharlesStOlive\FilamentTma\States\Ticket;

use CharlesStOlive\FilamentTma\Enums\Side;

class PendingValidation extends TicketState
{
    public static $name = 'pending_validation';

    public function getLabel(): string
    {
        return __('À valider');
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
        return __('Le travail est fait : le demandeur valide, ou rouvre.');
    }

    public function inHandsOf(): ?Side
    {
        return Side::Requester;
    }
}
