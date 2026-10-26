<?php

namespace CharlesStOlive\FilamentTma\Workflow\TaskMoves;

use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\States\Task\TaskDone;
use CharlesStOlive\FilamentTma\States\Task\TaskInProgress;
use CharlesStOlive\FilamentTma\Support\Html;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\TaskMove;
use CharlesStOlive\FilamentTma\Workflow\Timeline;
use Filament\Forms\Components\RichEditor;

class Complete extends TaskMove
{
    public function key(): string
    {
        return 'complete';
    }

    public function label(): string
    {
        return __('filament-tma::workflow.task.complete.label');
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

    /** @return array<string, mixed> */
    public function rules(ProjectTask $task): array
    {
        return ['message' => ['nullable', 'string', 'max:500000']];
    }

    public function formSchema(ProjectTask $task): array
    {
        return [
            RichEditor::make('message')->label(__('filament-tma::workflow.task.complete.message'))
                ->helperText(__('filament-tma::workflow.task.complete.message_help'))
                ->toolbarButtons([
                    ['bold', 'italic', 'underline', 'strike', 'link'],
                    ['bulletList', 'orderedList'],
                    ['undo', 'redo'],
                ]),
        ];
    }

    /** Terminée maintenant ; le mot de la fin, s'il y en a un, s'écrit avec le passage. */
    public function apply(ProjectTask $task, array $data, Context $context): void
    {
        $task->done_at = now();

        if (! Html::isBlank($data['message'] ?? null) && $context->actor !== null) {
            app(Timeline::class)->write($task, EntryType::Comment, (string) $data['message'], $context);
        }
    }
}
