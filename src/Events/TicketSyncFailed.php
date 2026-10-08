<?php

namespace CharlesStOlive\FilamentTma\Events;

use CharlesStOlive\FilamentTma\Models\Ticket;
use Illuminate\Foundation\Events\Dispatchable;

/** L'envoi d'un ticket à l'autre application a échoué après toutes ses tentatives. */
class TicketSyncFailed
{
    use Dispatchable;

    public function __construct(public Ticket $ticket, public string $error) {}
}
