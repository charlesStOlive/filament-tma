<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Projects\Pages;

use CharlesStOlive\FilamentTma\Filament\Resources\Projects\ProjectResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

/** Un nouveau titre part aussi chez le client, avec les tâches publiées du projet (voir `Project::booted()`). */
class EditProject extends EditRecord
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make(), DeleteAction::make()];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
