<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages;

use CharlesStOlive\FilamentTma\Enums\HandlingMode;
use CharlesStOlive\FilamentTma\Enums\SyncStatus;
use CharlesStOlive\FilamentTma\Filament\Actions\MoveAction;
use CharlesStOlive\FilamentTma\Filament\Actions\TimelineActions;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\TicketResource;
use CharlesStOlive\FilamentTma\Jobs\PushToInstance;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Support\TmaGate;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

/**
 * La fiche d'un ticket : la demande, son suivi, sa chronologie. En tête, les mouvements possibles maintenant pour
 * l'utilisateur, puis commenter et noter. Répondre et partager se lancent depuis la chronologie.
 */
class ViewTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->number.' — '.$this->getRecord()->title;
    }

    protected function getHeaderActions(): array
    {
        return [
            ...MoveAction::all(),
            TimelineActions::comment(),
            TimelineActions::agentTime(),
            ActionGroup::make([
                TimelineActions::note(),
                EditAction::make(),
                $this->resyncAction(),
            ]),
        ];
    }

    /** Maître : renvoyer un ticket du Support à son application (après une erreur, ou par précaution). */
    protected function resyncAction(): Action
    {
        return Action::make('resync')
            ->label(__('Renvoyer à l’application'))
            ->icon('heroicon-m-arrow-path')
            ->visible(fn (Ticket $record): bool => Tma::isMaster()
                && $record->handling_mode === HandlingMode::ServiceDesk
                && TmaGate::allows(auth()->user(), TmaGate::SYNC, $record))
            ->action(function (Ticket $record): void {
                $record->forceFill(['sync_status' => SyncStatus::Pending])->saveQuietly();
                rescue(function () use ($record): void {
                    PushToInstance::dispatch($record);
                });

                $record->refresh();
                Notification::make()
                    ->{$record->sync_status === SyncStatus::Error ? 'danger' : 'success'}()
                    ->title($record->sync_status === SyncStatus::Error ? $record->last_sync_error : __('Envoi demandé'))
                    ->send();
            });
    }

    public function replyAction(): Action
    {
        return TimelineActions::reply();
    }

    public function shareNoteAction(): Action
    {
        return TimelineActions::shareNote();
    }
}
