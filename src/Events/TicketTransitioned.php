<?php

namespace CharlesStOlive\FilamentTma\Events;

use CharlesStOlive\FilamentTma\Models\StateTransition;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Workflow\Context;
use Illuminate\Foundation\Events\Dispatchable;

/** Un mouvement du workflow a été fait : `transition` en garde la trace (mouvement, états, auteur, motif). */
class TicketTransitioned
{
    use Dispatchable;

    public function __construct(public Ticket $ticket, public StateTransition $transition, public Context $context) {}
}
