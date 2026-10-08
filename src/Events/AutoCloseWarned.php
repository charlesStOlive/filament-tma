<?php

namespace CharlesStOlive\FilamentTma\Events;

use CharlesStOlive\FilamentTma\Models\Ticket;
use Illuminate\Foundation\Events\Dispatchable;

/** Un ticket sans activité du demandeur sera clôturé le `closesOn` (avertissement de `tma:close-stale`). */
class AutoCloseWarned
{
    use Dispatchable;

    public function __construct(public Ticket $ticket, public \DateTimeInterface $closesOn) {}
}
