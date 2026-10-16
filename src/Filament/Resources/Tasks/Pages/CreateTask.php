<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Tasks\Pages;

use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\TaskPublication;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\TaskResource;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Recipients;
use CharlesStOlive\FilamentTma\Workflow\TaskWorkflow;
use CharlesStOlive\FilamentUi\OnDemand\HasOnDemand;
use CharlesStOlive\FilamentUi\OnDemand\OnDemandAction;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Sans date de publication, la tâche part tout de suite chez le client ; avec une date, elle reste en brouillon,
 * invisible du client, jusque-là (`TaskWorkflow::publishUnlessScheduled()`). Ses destinataires sont enregistrés avant
 * (au moins un principal chez le client, voir `TaskForm`). Les champs facultatifs (l'échéance) ne s'affichent qu'à la
 * demande : « Tout afficher ».
 */
class CreateTask extends CreateRecord
{
    use HasOnDemand;

    protected static string $resource = TaskResource::class;

    protected function getHeaderActions(): array
    {
        return [OnDemandAction::make()];
    }

    protected function handleRecordCreation(array $data): Model
    {
        $task = app(TaskWorkflow::class)->create($data, Context::current());
        app(Recipients::class)->update($task, (array) ($data['primary_recipients'] ?? []), (array) ($data['secondary_recipients'] ?? []), Context::current());

        return $task;
    }

    protected function afterCreate(): void
    {
        TaskPublication::afterSave($this->getRecord());
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
