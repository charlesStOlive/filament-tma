<?php

namespace CharlesStOlive\FilamentTma\Events;

use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Workflow\Context;
use Illuminate\Foundation\Events\Dispatchable;

class TaskCreated
{
    use Dispatchable;

    public function __construct(public ProjectTask $task, public ?Context $context = null) {}
}
