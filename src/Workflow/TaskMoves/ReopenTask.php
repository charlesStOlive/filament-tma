<?php

namespace CharlesStOlive\FilamentTma\Workflow\TaskMoves;

use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\States\Task\TaskCancelled;
use CharlesStOlive\FilamentTma\States\Task\TaskDone;
use CharlesStOlive\FilamentTma\States\Task\TaskInProgress;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\TaskMove;
use Filament\Forms\Components\Textarea;

/**
 * Rouvrir une tâche terminée ou annulée : elle repart En cours, chez le client aussi, avec son historique. Réservé au
 * Support (le droit `tma.task.workflow.reopen`), dans le maître ou, pour un membre du Support, depuis l'application
 * cliente.
 */
class ReopenTask extends TaskMove
{
    public function key(): string
    {
        return 'reopen';
    }

    public function label(): string
    {
        return __('filament-tma::workflow.task.reopen.label');
    }

    public function description(): ?string
    {
        return __('filament-tma::workflow.task.reopen.description');
    }

    public function color(): string
    {
        return 'warning';
    }

    public function icon(): ?string
    {
        return 'heroicon-m-arrow-uturn-left';
    }

    public function from(): array
    {
        return [TaskDone::class, TaskCancelled::class];
    }

    public function to(): string
    {
        return TaskInProgress::class;
    }

    public function requiresConfirmation(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(ProjectTask $task): array
    {
        return ['reason' => ['nullable', 'string', 'max:2000']];
    }

    public function formSchema(ProjectTask $task): array
    {
        return [Textarea::make('reason')->label(__('filament-tma::workflow.task.reopen.reason'))->rows(3)->maxLength(2000)];
    }

    public function apply(ProjectTask $task, array $data, Context $context): void
    {
        $task->done_at = null;
        $task->cancelled_at = null;
    }
}
