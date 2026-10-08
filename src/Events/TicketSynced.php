<?php

namespace CharlesStOlive\FilamentTma\Events;

use CharlesStOlive\FilamentTma\Models\Ticket;
use Illuminate\Foundation\Events\Dispatchable;

/** L'état d'un ticket vient d'être reçu de l'autre application. */
class TicketSynced
{
    use Dispatchable;

    public function __construct(public Ticket $ticket) {}
}
