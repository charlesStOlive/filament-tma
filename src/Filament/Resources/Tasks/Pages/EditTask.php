<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Tasks\Pages;

use CharlesStOlive\FilamentTma\Events\TaskUpdated;
use CharlesStOlive\FilamentTma\Filament\Actions\TaskDeleteActions;
use CharlesStOlive\FilamentTma\Filament\Concerns\HasOrthography;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\TaskPublication;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\TaskResource;
use CharlesStOlive\FilamentTma\Models\Project;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Recipients;
use CharlesStOlive\FilamentUi\OnDemand\HasOnDemand;
use CharlesStOlive\FilamentUi\OnDemand\OnDemandAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

/**
 * Un brouillon enregistré sans date de publication part chez le client (voir `CreateTask`), ses destinataires
 * enregistrés avant. Les champs facultatifs (l'échéance) ne s'affichent qu'à la demande : « Tout afficher ».
 */
class EditTask extends EditRecord
{
    use HasOnDemand;
    use HasOrthography;

    protected static string $resource = TaskResource::class;

    /** @var array{primary_recipients: array<int, string>, secondary_recipients: array<int, string>} */
    private array $recipientKeys = ['primary_recipients' => [], 'secondary_recipients' => []];

    protected function getHeaderActions(): array
    {
        return [...$this->orthographyActions('form', ['title' => __('Titre'), 'description' => __('Description')]), OnDemandAction::make(), ViewAction::make(), TaskDeleteActions::make()];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [...$data, ...app(Recipients::class)->keys($this->getRecord())];
    }

    /** Un brouillon qui change de projet change aussi d'application ; une tâche publiée garde la sienne. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $project = Project::query()->find($data['project_id'] ?? null);

        if ($project !== null && ! $this->getRecord()->isShared()) {
            $data['target_instance_uid'] = $project->target_instance_uid;
        }

        $this->recipientKeys = [
            'primary_recipients' => (array) ($data['primary_recipients'] ?? []),
            'secondary_recipients' => (array) ($data['secondary_recipients'] ?? []),
        ];
        unset($data['primary_recipients'], $data['secondary_recipients']);

        return $data;
    }

    protected function afterSave(): void
    {
        $task = $this->getRecord();
        $task->touchActivity();
        app(Recipients::class)->update($task, $this->recipientKeys['primary_recipients'], $this->recipientKeys['secondary_recipients'], Context::current());

        if (! TaskPublication::afterSave($task)) {
            event(new TaskUpdated($task, Context::current()));
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
