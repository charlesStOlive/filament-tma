<?php

namespace CharlesStOlive\FilamentTma\States\Ticket;

use CharlesStOlive\FilamentTma\Enums\Side;

class Closed extends TicketState
{
    public static $name = 'closed';

    public function getLabel(): string
    {
        return __('Clôturé');
    }

    public function getColor(): string
    {
        return 'success';
    }

    public function getIcon(): string
    {
        return 'heroicon-m-check-circle';
    }

    public function getDescription(): ?string
    {
        return __('Terminé. C’est l’état qui ouvre la facturation complète.');
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
