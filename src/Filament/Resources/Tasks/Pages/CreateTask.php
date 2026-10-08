<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Tasks\Pages;

use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\TaskResource;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\TaskWorkflow;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/** Une tâche commence en brouillon, invisible du client (`TaskWorkflow::create()`). */
class CreateTask extends CreateRecord
{
    protected static string $resource = TaskResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(TaskWorkflow::class)->create($data, Context::current());
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
