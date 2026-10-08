<?php

namespace CharlesStOlive\FilamentTma\Events;

use CharlesStOlive\FilamentTma\Contracts\Trackable;
use Illuminate\Foundation\Events\Dispatchable;

/** L'envoi d'un ticket (ou d'une tâche) à l'autre application a échoué après toutes ses tentatives. */
class TicketSyncFailed
{
    use Dispatchable;

    public function __construct(public Trackable $ticket, public string $error) {}
}
