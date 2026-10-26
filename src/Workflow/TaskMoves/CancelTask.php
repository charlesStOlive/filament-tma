<?php

namespace CharlesStOlive\FilamentTma\Workflow\TaskMoves;

use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\States\Task\TaskCancelled;
use CharlesStOlive\FilamentTma\States\Task\TaskDraft;
use CharlesStOlive\FilamentTma\States\Task\TaskInProgress;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\TaskMove;
use Filament\Forms\Components\Textarea;

/** Abandonner la tâche, avec son motif (sauf en brouillon) ; elle garde son historique. */
class CancelTask extends TaskMove
{
    public function key(): string
    {
        return 'cancel';
    }

    public function label(): string
    {
        return __('filament-tma::workflow.task.cancel.label');
    }

    public function color(): string
    {
        return 'danger';
    }

    public function icon(): ?string
    {
        return 'heroicon-m-x-mark';
    }

    public function from(): array
    {
        return [TaskDraft::class, TaskInProgress::class];
    }

    public function to(): string
    {
        return TaskCancelled::class;
    }

    public function rules(ProjectTask $task): array
    {
        return ['reason' => [$task->state instanceof TaskDraft ? 'nullable' : 'required', 'string', 'max:2000']];
    }

    public function formSchema(ProjectTask $task): array
    {
        return [Textarea::make('reason')->label(__('filament-tma::workflow.reason'))->required(! $task->state instanceof TaskDraft)->rows(3)->maxLength(2000)];
    }

    public function apply(ProjectTask $task, array $data, Context $context): void
    {
        $task->cancelled_at = now();
        $task->publish_at = null;
    }
}
