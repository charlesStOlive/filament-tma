<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages;

use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\TicketResource;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\TicketWorkflow;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Sync\RemoteUsers;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\Model;

/**
 * Une demande commence en brouillon, au nom de l'utilisateur connecté (`TicketWorkflow::create()`). Dans le maître,
 * c'est un ticket préparé pour un client, au nom de l'un de ses utilisateurs (`createForClient()`).
 */
class CreateTicket extends CreateRecord
{
    protected static string $resource = TicketResource::class;

    protected function handleRecordCreation(array $data): Model
    {
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
