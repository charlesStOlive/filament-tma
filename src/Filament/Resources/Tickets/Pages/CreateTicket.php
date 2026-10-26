<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages;

use CharlesStOlive\FilamentTma\Enums\Priority;
use CharlesStOlive\FilamentTma\Filament\Concerns\HasOrthography;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\TaskResource;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\TicketResource;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Support\Terms;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Sync\RemoteUsers;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\TicketWorkflow;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;

/**
 * Une demande commence en brouillon, au nom de l'utilisateur connecté (`TicketWorkflow::create()`). Dans le maître,
 * c'est un ticket préparé pour un client (`createForClient()`) : au nom de l'un de ses correspondants, ou le Support
 * qui s'adresse à lui, ou qui l'informe (`Enums\TicketOpening`) ; publié tout de suite, ou gardé en brouillon.
 *
 * Depuis une tâche projet (`?task=…`, le bouton « Créer un ticket » de sa fiche), le ticket en reprend le titre, la
 * description et l'application, et reste lié à elle. Dans le maître, `?instance=…` choisit d'avance l'application : un
 * membre du Support y arrive depuis l'application cliente (`Support\MasterLinks`).
 *
 * « Orthographe » corrige le titre et la description, quand l'IA est là (`HasOrthography`).
 */
class CreateTicket extends CreateRecord
{
    use HasOrthography;

    protected static string $resource = TicketResource::class;

    #[Url(as: 'task')]
    public ?string $taskId = null;

    /** Maître : l'application du client, choisie d'avance (le lien « Nouvelle demande » d'un membre du Support chez lui). */
    #[Url(as: 'instance')]
    public ?string $instanceUid = null;

    /** La tâche dont part le ticket (par son id, ou son ulid depuis une application cliente), si l'utilisateur la voit. */
    public function sourceTask(): ?ProjectTask
    {
        $task = match (true) {
            blank($this->taskId) => null,
            ctype_digit((string) $this->taskId) => ProjectTask::query()->find($this->taskId),
            default => ProjectTask::query()->where('ulid', strtolower((string) $this->taskId))->first(),
        };

        return $task !== null && TaskResource::canView($task) ? $task : null;
    }

    protected function afterFill(): void
    {
        $task = $this->sourceTask();

        if ($task === null) {
            if (Tma::isMaster() && filled($this->instanceUid) && Instance::forUid($this->instanceUid) !== null) {
                $this->form->fill([...$this->form->getRawState(), 'instance_uid' => $this->instanceUid]);
            }

            return;
        }

        $this->form->fill([
            'title' => $task->title,
            'description' => $task->description,
            'priority' => Priority::Normal->value,
            'instance_uid' => Tma::isMaster() ? $task->target_instance_uid : null,
        ]);
    }

    /** « Publier chez EDM » plutôt qu'« Enregistrer le brouillon » (maître). */
    public bool $publishNow = false;

    protected function getHeaderActions(): array
    {
        return $this->orthographyActions('form', ['title' => __('filament-tma::common.title'), 'description' => __('filament-tma::common.description')]);
    }

    /**
     * Dans le maître, deux façons d'enregistrer : « Publier chez EDM » (le client le voit tout de suite) ou
     * « Enregistrer le brouillon » (invisible du client, publié à la main ou à la date programmée).
     */
    protected function getFormActions(): array
    {
        if (! Tma::isMaster()) {
            return parent::getFormActions();
        }

        return [
            Action::make('createAndPublish')
                ->label(fn (): string => Terms::publish($this->data['instance_uid'] ?? null))
                ->icon('heroicon-m-megaphone')
                ->action(function (): void {
                    $this->publishNow = true;
                    $this->create();
                }),
            $this->getCreateFormAction()->label(__('filament-tma::tickets.actions.save_draft'))->color('gray'),
            $this->getCancelFormAction(),
        ];
    }

    protected function afterCreate(): void
    {
        /** @var Ticket $ticket */
        $ticket = $this->getRecord();

        if ($this->publishNow && Tma::isMaster()) {
            app(TicketWorkflow::class)->run($ticket, 'publish', [], Context::current());
        }
    }

    public function getSubheading(): ?string
    {
        $task = $this->sourceTask();

        return $task === null ? null : __('filament-tma::tickets.from_task_named', ['task' => $task->number.' — '.$task->title]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $data['task_id'] = $this->sourceTask()?->getKey();

        if (Tma::isMaster()) {
            $instance = Instance::query()->where('uid', $data['instance_uid'])->firstOrFail();
            $requester = collect(RemoteUsers::for($instance))->firstWhere('id', (string) $data['requester_id'])
                ?? throw ValidationException::withMessages(['data.requester_id' => __('filament-tma::tickets.errors.requester_gone')]);

            return app(TicketWorkflow::class)->createForClient($instance, $requester, $data, Context::current());
        }

        return app(TicketWorkflow::class)->create($data, Context::current());
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
