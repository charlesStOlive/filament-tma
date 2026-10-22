<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Projects\Pages;

use CharlesStOlive\FilamentTma\Filament\Resources\Projects\ProjectResource;
use CharlesStOlive\FilamentTma\Models\Project;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Un projet en pleine page (d'ordinaire, on le modifie dans son volet : `ProjectResource::editAction()`). Un changement
 * part aussi chez le client, avec les tâches publiées du projet (voir `Project::pushToClient()`).
 */
class EditProject extends EditRecord
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make(), DeleteAction::make()];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [...$data, ...ProjectResource::formData($this->getRecord())];
    }

    /** @param  Project  $record */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return ProjectResource::updateProject($record, $data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
