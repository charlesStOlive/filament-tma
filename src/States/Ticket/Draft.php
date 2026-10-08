<?php

namespace CharlesStOlive\FilamentTma\States\Ticket;

use CharlesStOlive\FilamentTma\Enums\Side;

class Draft extends TicketState
{
    public static $name = 'draft';

    public function getLabel(): string
    {
        return __('Brouillon');
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
        return __('En préparation : visible de son auteur et de son application seulement.');
    }

    public function inHandsOf(): ?Side
    {
        return Side::Requester;
    }
}
