<?php

namespace CharlesStOlive\FilamentTma\Workflow\Concerns;

use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Workflow\Context;

/**
 * Le Support répond à une demande nouvelle (une question, « résolu », une pause) : plus besoin de la « prendre en
 * charge », son contact est choisi à la transmission (addendum §3.1). On garde quand il a répondu la première fois, et,
 * s'il n'y avait pas de contact, celui qui répond le devient.
 */
trait AnswersTheRequest
{
    protected function answering(Ticket $ticket, Context $context): void
    {
        $ticket->taken_at ??= now();
        $ticket->assignee_actor_id ??= $context->actor?->getKey();
    }
}
