<?php

namespace CharlesStOlive\FilamentTma\States\Task;

class TaskDone extends TaskState
{
    public static $name = 'done';

    public function getLabel(): string
    {
        return __('filament-tma::states.task.done.label');
    }

    public function getColor(): string
    {
        return 'success';
    }

    public function getIcon(): string
    {
        return 'heroicon-m-check-circle';
    }

    public function getDescription(): ?string
    {
        return __('filament-tma::states.task.done.description');
    }

    public function isFinal(): bool
    {
        return true;
    }
}
