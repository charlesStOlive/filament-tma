<?php

namespace CharlesStOlive\FilamentTma\Workflow\Moves;

use CharlesStOlive\FilamentTma\Models\Ticket;

/** La publication programmée d'un brouillon du maître est arrivée (`tma:publish-scheduled`). */
class AutoPublish extends Publish
{
    public function key(): string
    {
        return 'auto-publish';
    }

    public function label(): string
    {
        return __('Publication programmée');
    }

    public function sides(): array
    {
        return [];
    }

    public function isAvailable(Ticket $ticket): bool
    {
        return parent::isAvailable($ticket) && $ticket->publish_at !== null && $ticket->publish_at->isPast();
    }
}
