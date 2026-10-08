<?php

namespace CharlesStOlive\FilamentTma\Events;

use CharlesStOlive\FilamentTma\Models\ProjectTask;
use Illuminate\Foundation\Events\Dispatchable;

/** Côté client : une tâche projet vient d'être mise à disposition par le maître (CDC §22). */
class TaskReceived
{
    use Dispatchable;

    public function __construct(public ProjectTask $task) {}
}
