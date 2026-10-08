<?php

namespace CharlesStOlive\FilamentTma\Workflow\TaskMoves;

use CharlesStOlive\FilamentTma\Models\ProjectTask;

/** La publication programmée est arrivée (`tma:publish-scheduled`). */
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

    public function isSystem(): bool
    {
        return true;
    }

    public function isAvailable(ProjectTask $task): bool
    {
        return $task->publish_at !== null && $task->publish_at->isPast();
    }
}
