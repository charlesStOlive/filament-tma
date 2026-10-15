<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Tasks\Pages;

use CharlesStOlive\FilamentTma\Events\TaskUpdated;
use CharlesStOlive\FilamentTma\Filament\Actions\TaskDeleteActions;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\TaskPublication;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\TaskResource;
use CharlesStOlive\FilamentTma\Models\Project;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentUi\OnDemand\HasOnDemand;
use CharlesStOlive\FilamentUi\OnDemand\OnDemandAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

/**
 * Un brouillon enregistré sans date de publication part chez le client (voir `CreateTask`). Les champs facultatifs
 * (l'échéance) ne s'affichent qu'à la demande : « Tout afficher ».
 */
class EditTask extends EditRecord
{
    use HasOnDemand;

    protected static string $resource = TaskResource::class;

    protected function getHeaderActions(): array
    {
        return [OnDemandAction::make(), ViewAction::make(), TaskDeleteActions::make()];
    }

    /** Un brouillon qui change de projet change aussi d'application ; une tâche publiée garde la sienne. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $project = Project::query()->find($data['project_id'] ?? null);

        if ($project !== null && ! $this->getRecord()->isShared()) {
            $data['target_instance_uid'] = $project->target_instance_uid;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $this->getRecord()->touchActivity();

        if (! TaskPublication::afterSave($this->getRecord())) {
            event(new TaskUpdated($this->getRecord(), Context::current()));
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
