<?php

namespace CharlesStOlive\FilamentTma\Listeners;

use CharlesStOlive\FilamentTma\Events\TicketTransitioned;
use CharlesStOlive\FilamentTma\Notifications\TicketAutoClosed;

/**
 * Prévient le demandeur d'une clôture automatique — quand il est d'ici : chaque application notifie ses propres
 * utilisateurs (addendum §4). Pour un ticket du centre de service, c'est l'application cliente qui le fera en
 * recevant la transition (lot 1b).
 */
class NotifyAutoClosure
{
    public function handle(TicketTransitioned $event): void
    {
        if ($event->transition->move !== 'auto-close') {
            return;
        }

        $user = $event->ticket->requester?->isLocal() ? $event->ticket->requester->user : null;

        $user?->notify(new TicketAutoClosed($event->ticket));
    }
}
