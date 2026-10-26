<?php

namespace CharlesStOlive\FilamentTma\States\Task;

class TaskInProgress extends TaskState
{
    public static $name = 'in_progress';

    public function getLabel(): string
    {
        return __('filament-tma::common.in_progress');
    }

    public function getColor(): string
    {
        return 'primary';
    }

    public function getIcon(): string
    {
        return 'heroicon-m-wrench-screwdriver';
    }

    public function getDescription(): ?string
    {
        return __('filament-tma::states.task.in_progress.description');
    }
}
