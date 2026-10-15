<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Tasks\Pages;

use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\TaskResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTasks extends ListRecords
{
    protected static string $resource = TaskResource::class;

    protected function getHeaderActions(): array
    {
        // Les tâches se créent chez le Support : Filament n'autorise le bouton que par la policy, pas par `canCreate()`.
        return [CreateAction::make()->visible(fn (): bool => TaskResource::canCreate())];
    }
}
