<?php

namespace CharlesStOlive\FilamentTma\Workflow\TaskMoves;

use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\States\Task\TaskDraft;
use CharlesStOlive\FilamentTma\States\Task\TaskInProgress;
use CharlesStOlive\FilamentTma\Support\Terms;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\TaskMove;

/** « Publier chez EDM » : met la tâche à disposition de son application cliente ; elle passe En cours et part chez le client. */
class Publish extends TaskMove
{
    public function key(): string
    {
        return 'publish';
    }

    public function label(): string
    {
        return Terms::publish();
    }

    public function labelFor(ProjectTask $task): string
    {
        return Terms::publish($task->target_instance_uid);
    }

    public function description(): ?string
    {
        return __('La tâche devient visible dans l’application du client.');
    }

    public function icon(): ?string
    {
        return 'heroicon-m-megaphone';
    }

    public function from(): array
    {
        return [TaskDraft::class];
    }

    public function to(): string
    {
        return TaskInProgress::class;
    }

    public function requiresConfirmation(): bool
    {
        return true;
    }

    public function apply(ProjectTask $task, array $data, Context $context): void
    {
        $task->published_at = $this->happenedAt($task);
        $task->publish_at = null;
    }
}
