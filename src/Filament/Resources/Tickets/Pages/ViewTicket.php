<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages;

use CharlesStOlive\FilamentTma\Enums\HandlingMode;
use CharlesStOlive\FilamentTma\Enums\SyncStatus;
use CharlesStOlive\FilamentTma\Filament\Actions\MoveAction;
use CharlesStOlive\FilamentTma\Filament\Concerns\HasComposer;
use CharlesStOlive\FilamentTma\Filament\Concerns\HasInfoPane;
use CharlesStOlive\FilamentTma\Filament\Concerns\HasNotes;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Schemas\TicketInfolist;
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
use Filament\Schemas\Components\Component;

/**
 * La fiche d'un ticket : l'historique (la demande, puis les échanges) et, dessous, le nouveau message (`HasComposer`,
 * avec les réponses de la partie qu'on attend) ; à droite, le suivi, le temps passé et les notes (`HasInfoPane`). En
 * tête, les passages possibles maintenant pour l'utilisateur. Les notes et la modification d'un message s'ouvrent en
 * fenêtres (`HasNotes`).
 */
class ViewTicket extends ViewRecord
{
    use HasComposer;
    use HasInfoPane;
    use HasNotes;

    protected static string $resource = TicketResource::class;

    /** Le temps passé en tête dans le maître ; chez le client, il ferme le volet. */
    protected function sideComponents(Component $time): array
    {
        return Tma::isMaster() ? [$time, ...TicketInfolist::side()] : [...TicketInfolist::side(), $time];
    }

    public function getTitle(): string
    {
        return $this->getRecord()->number.' — '.$this->getRecord()->title;
    }

    protected function getHeaderActions(): array
    {
        return [
            ...MoveAction::all(),
            ActionGroup::make([
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
}
