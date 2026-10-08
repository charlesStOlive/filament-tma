<?php

namespace CharlesStOlive\FilamentTma\Support;

use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\TicketResource;
use CharlesStOlive\FilamentTma\Models\Ticket;
use Filament\Facades\Filament;

/** L'adresse de la fiche d'un ticket, aussi hors d'une requête Filament (e-mail, commande). */
final class TicketUrl
{
    public static function view(Ticket $ticket): string
    {
        $panel = Filament::getCurrentOrDefaultPanel();

        return TicketResource::getUrl('view', ['record' => $ticket], panel: $panel?->getId());
    }
}
