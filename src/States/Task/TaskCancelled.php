<?php

namespace CharlesStOlive\FilamentTma\States\Task;

class TaskCancelled extends TaskState
{
    public static $name = 'cancelled';

    public function getLabel(): string
    {
        return __('filament-tma::states.task.cancelled.label');
    }

    public function getColor(): string
    {
        return 'danger';
    }

    public function getIcon(): string
    {
        return 'heroicon-m-x-circle';
    }

    public function getDescription(): ?string
    {
        return __('filament-tma::states.task.cancelled.description');
    }

    public function isFinal(): bool
    {
        return true;
    }
}
