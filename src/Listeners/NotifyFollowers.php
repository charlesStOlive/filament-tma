<?php

namespace CharlesStOlive\FilamentTma\Listeners;

use CharlesStOlive\FilamentTma\Events\EntryAdded;
use CharlesStOlive\FilamentTma\Events\SnapshotMirrored;
use CharlesStOlive\FilamentTma\Events\TaskTransitioned;
use CharlesStOlive\FilamentTma\Events\TicketTransitioned;
use CharlesStOlive\FilamentTma\Support\Notifier;

/**
 * Prévient les utilisateurs d'ici (`Support\Notifier`) : d'un passage ou d'une entrée faits ici, ou reçus dans le
 * maître par son API ; de ce qu'apporte l'état recopié de l'autre application.
 */
class NotifyFollowers
{
    public function handle(TicketTransitioned|TaskTransitioned|EntryAdded|SnapshotMirrored $event): void
    {
        match (true) {
            $event instanceof TicketTransitioned => Notifier::transition($event->ticket, $event->transition),
            $event instanceof TaskTransitioned => Notifier::transition($event->task, $event->transition),
            $event instanceof EntryAdded => Notifier::entry($event->entry),
            $event instanceof SnapshotMirrored => Notifier::received($event->subject, $event->transitions, $event->entries),
        };
    }
}
