<?php

namespace CharlesStOlive\FilamentTma\States\Task;

class TaskDraft extends TaskState
{
    public static $name = 'draft';

    public function getLabel(): string
    {
        return __('Brouillon');
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
        return __('Visible dans le centre de service seulement ; pas encore chez le client.');
    }
}
