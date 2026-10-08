<?php

namespace CharlesStOlive\FilamentTma\Events;

use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Models\StateTransition;
use CharlesStOlive\FilamentTma\Workflow\Context;
use Illuminate\Foundation\Events\Dispatchable;

/** Une tâche projet a changé d'état (publiée, terminée, annulée). */
class TaskTransitioned
{
    use Dispatchable;

    public function __construct(public ProjectTask $task, public StateTransition $transition, public Context $context) {}
}
