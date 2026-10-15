<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Projects\Pages;

use CharlesStOlive\FilamentTma\Filament\Resources\Projects\ProjectResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/** Un projet : son application, son temps (la somme de celui de ses tâches), puis ses tâches. */
class ViewProject extends ViewRecord
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
