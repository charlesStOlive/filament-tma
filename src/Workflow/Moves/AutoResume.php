<?php

namespace CharlesStOlive\FilamentTma\Workflow\Moves;

use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\States\Ticket\Paused;

/** La date de reprise d'une pause est arrivée : le planificateur reprend le ticket (`tma:resume-paused`). */
class AutoResume extends Resume
{
    public function key(): string
    {
        return 'auto-resume';
    }

    public function label(): string
    {
        return __('Reprise à la date prévue');
    }

    public function from(): array
    {
        return [Paused::class];
    }

    public function sides(): array
    {
        return [];
    }

    public function isAvailable(Ticket $ticket): bool
    {
        return $ticket->authorityIsHere() && $ticket->paused_until !== null && $ticket->paused_until->isPast();
    }
}
