<?php

namespace CharlesStOlive\FilamentTma\Events;

use CharlesStOlive\FilamentTma\Models\Ticket;
use Illuminate\Foundation\Events\Dispatchable;

/** Les champs d'un ticket (titre, description, priorité…) ont été modifiés ici. */
class TicketUpdated
{
    use Dispatchable;

    public function __construct(public Ticket $ticket) {}
}
