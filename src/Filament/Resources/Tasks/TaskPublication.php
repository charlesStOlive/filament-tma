<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Tasks;

use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\States\Task\TaskDraft;
use CharlesStOlive\FilamentTma\Support\Terms;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\TaskWorkflow;
use Filament\Notifications\Notification;

/** Après l'enregistrement d'une tâche (création ou modification) : la publier tout de suite si elle n'a pas de date. */
final class TaskPublication
{
    /** Vrai si la tâche vient de partir chez le client. */
    public static function afterSave(ProjectTask $task): bool
    {
        if (app(TaskWorkflow::class)->publishUnlessScheduled($task, Context::current())) {
            Notification::make()->success()
                ->title(__('Publiée chez :client', ['client' => Terms::clientOrInstance($task->target_instance_uid)]))
                ->send();

            return true;
        }

        if ($task->state instanceof TaskDraft && $task->publish_at === null) {
            Notification::make()->warning()
                ->title(__('Restée en brouillon : vous ne pouvez pas la publier.'))
                ->send();
        }

        return false;
    }
}
