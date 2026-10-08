<?php

namespace CharlesStOlive\FilamentTma\Listeners;

use CharlesStOlive\FilamentTma\Events\TicketTransitioned;
use CharlesStOlive\FilamentTma\Support\Notifier;

/**
 * Prévient les utilisateurs d'ici d'un passage fait ici : la clôture automatique toujours, les autres selon
 * `notifications.immediate` (voir `Support\Notifier`). Un passage reçu de l'autre application est traité à sa
 * réception (`SyncController`).
 */
class NotifyAutoClosure
{
    public function handle(TicketTransitioned $event): void
    {
        Notifier::transition($event->ticket, $event->transition);
    }
}
