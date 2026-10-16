<?php

namespace CharlesStOlive\FilamentTma\Events;

use CharlesStOlive\FilamentTma\Contracts\Trackable;
use CharlesStOlive\FilamentTma\Workflow\Context;
use Illuminate\Foundation\Events\Dispatchable;

/** Les destinataires d'un ticket ou d'une tâche ont changé (`Workflow\Recipients`). */
class RecipientsChanged
{
    use Dispatchable;

    public function __construct(public Trackable $subject, public ?Context $context = null) {}
}
