<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Projects\Pages;

use CharlesStOlive\FilamentTma\Filament\Resources\Projects\ProjectResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/** Un projet pour une application cliente (et son client, repris de l'application). */
class CreateProject extends CreateRecord
{
    protected static string $resource = ProjectResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return ProjectResource::createProject($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
