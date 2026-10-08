<?php

namespace CharlesStOlive\FilamentTma\Workflow\TaskMoves;

use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\States\Task\TaskDone;
use CharlesStOlive\FilamentTma\States\Task\TaskInProgress;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\TaskMove;

class Complete extends TaskMove
{
    public function key(): string
    {
        return 'complete';
    }

    public function label(): string
    {
        return __('Terminer');
    }

    public function color(): string
    {
        return 'success';
    }

    public function icon(): ?string
    {
        return 'heroicon-m-check-circle';
    }

    public function from(): array
    {
        return [TaskInProgress::class];
    }

    public function to(): string
    {
        return TaskDone::class;
    }

    public function requiresConfirmation(): bool
    {
        return true;
    }

    public function apply(ProjectTask $task, array $data, Context $context): void
    {
        $task->done_at = now();
    }
}
