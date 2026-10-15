<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages;

use CharlesStOlive\FilamentTma\Enums\Priority;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\TaskResource;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\TicketResource;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Sync\RemoteUsers;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\TicketWorkflow;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;

/**
 * Une demande commence en brouillon, au nom de l'utilisateur connecté (`TicketWorkflow::create()`). Dans le maître,
 * c'est un ticket préparé pour un client, au nom de l'un de ses utilisateurs (`createForClient()`).
 *
 * Depuis une tâche projet (`?task=…`, le bouton « Créer un ticket » de sa fiche), le ticket en reprend le titre, la
 * description et l'application, et reste lié à elle.
 */
class CreateTicket extends CreateRecord
{
    protected static string $resource = TicketResource::class;

    #[Url(as: 'task')]
    public ?string $taskId = null;

    /** La tâche dont part le ticket, si l'utilisateur la voit. */
    public function sourceTask(): ?ProjectTask
    {
        $task = filled($this->taskId) ? ProjectTask::query()->find($this->taskId) : null;

        return $task !== null && TaskResource::canView($task) ? $task : null;
    }

    protected function afterFill(): void
    {
        $task = $this->sourceTask();

        if ($task === null) {
            return;
        }

        $this->form->fill([
            'title' => $task->title,
            'description' => $task->description,
            'priority' => Priority::Normal->value,
            'instance_uid' => Tma::isMaster() ? $task->target_instance_uid : null,
        ]);
    }

    public function getSubheading(): ?string
    {
        $task = $this->sourceTask();

        return $task === null ? null : __('À partir de la tâche :task', ['task' => $task->number.' — '.$task->title]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $data['task_id'] = $this->sourceTask()?->getKey();

        if (Tma::isMaster()) {
            $instance = Instance::query()->where('uid', $data['instance_uid'])->firstOrFail();
            $requester = collect(RemoteUsers::for($instance))->firstWhere('id', (string) $data['requester_id'])
                ?? throw ValidationException::withMessages(['data.requester_id' => __('Ce demandeur n’est plus dans l’application.')]);

            return app(TicketWorkflow::class)->createForClient($instance, $requester, $data, Context::current());
        }

        return app(TicketWorkflow::class)->create($data, Context::current());
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
