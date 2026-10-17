<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages;

use CharlesStOlive\FilamentTma\Events\TicketUpdated;
use CharlesStOlive\FilamentTma\Filament\Concerns\HasOrthography;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\TicketResource;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Sync\MasterClient;
use CharlesStOlive\FilamentTma\Sync\Mirror;
use CharlesStOlive\FilamentTma\Sync\SyncException;
use CharlesStOlive\FilamentTma\Workflow\Context;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

/**
 * Modifier un ticket (addendum §3.2) : son auteur modifie la demande en brouillon, puis tant qu'aucun message ne l'a
 * suivie ; le Support, à son tour, retouche le titre et la priorité. Après la transmission, l'application cliente
 * envoie la demande au maître dans la même transaction : s'il la refuse ou ne répond pas, rien n'est gardé.
 * « Orthographe » corrige ce qui reste modifiable (`HasOrthography`).
 */
class EditTicket extends EditRecord
{
    use HasOrthography;

    protected static string $resource = TicketResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function getHeaderActions(): array
    {
        return [
            ...$this->orthographyActions('form', ['title' => __('Titre'), 'description' => __('Description')]),
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        /** @var Ticket $ticket */
        $ticket = $this->getRecord();

        if (Tma::isClient() && $ticket->isShared()) {
            try {
                Mirror::snapshot(app(MasterClient::class)->updateDemand($ticket, Context::current()));
            } catch (SyncException $e) {
                Notification::make()->danger()->title($e->getMessage())->send();
                $this->halt(true);
            }

            return;
        }

        $ticket->touchActivity();
        event(new TicketUpdated($ticket));
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
