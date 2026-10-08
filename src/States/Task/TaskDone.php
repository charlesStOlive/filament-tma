<?php

namespace CharlesStOlive\FilamentTma\States\Task;

class TaskDone extends TaskState
{
    public static $name = 'done';

    public function getLabel(): string
    {
        return __('Terminée');
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
        return __('Achevée : elle peut être facturée entièrement.');
    }

    public function isFinal(): bool
    {
        return true;
    }
}
