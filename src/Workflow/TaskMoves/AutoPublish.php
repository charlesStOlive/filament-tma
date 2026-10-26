<?php

namespace CharlesStOlive\FilamentTma\Workflow\TaskMoves;

use Carbon\CarbonInterface;
use CharlesStOlive\FilamentTma\Models\ProjectTask;

/**
 * La publication programmée est arrivée (`tma:publish-scheduled`) : la tâche compte comme publiée à l'heure dite
 * (`publish_at`), pas à la minute où passe le planificateur. Sa date, son passage et sa dernière activité le disent.
 */
class AutoPublish extends Publish
{
    public function key(): string
    {
        return 'auto-publish';
    }

    public function label(): string
    {
        return __('filament-tma::common.scheduled_publication');
    }

    public function isSystem(): bool
    {
        return true;
    }

    public function isAvailable(ProjectTask $task): bool
    {
        return $task->publish_at !== null && $task->publish_at->isPast();
    }

    public function happenedAt(ProjectTask $task): CarbonInterface
    {
        return $task->published_at ?? $task->publish_at ?? now();
    }
}
