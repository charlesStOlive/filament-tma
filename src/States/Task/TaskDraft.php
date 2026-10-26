<?php

namespace CharlesStOlive\FilamentTma\States\Task;

class TaskDraft extends TaskState
{
    public static $name = 'draft';

    public function getLabel(): string
    {
        return __('filament-tma::states.task.draft.label');
    }

    public function getColor(): string
    {
        return 'gray';
    }

    public function getIcon(): string
    {
        return 'heroicon-m-pencil-square';
    }

    public function getDescription(): ?string
    {
        return __('filament-tma::states.task.draft.description');
    }
}
