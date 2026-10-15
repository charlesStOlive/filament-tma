<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Tasks\Pages;

use CharlesStOlive\FilamentTma\Enums\SyncStatus;
use CharlesStOlive\FilamentTma\Filament\Actions\TaskDeleteActions;
use CharlesStOlive\FilamentTma\Filament\Actions\TaskMoveAction;
use CharlesStOlive\FilamentTma\Filament\Concerns\HasComposer;
use CharlesStOlive\FilamentTma\Filament\Concerns\HasInfoPane;
use CharlesStOlive\FilamentTma\Filament\Concerns\HasNotes;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\Schemas\TaskInfolist;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\TaskResource;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\TicketResource;
use CharlesStOlive\FilamentTma\Jobs\PushToInstance;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Support\TmaGate;
use CharlesStOlive\FilamentUi\OnDemand\HasOnDemand;
use CharlesStOlive\FilamentUi\OnDemand\OnDemandAction;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Component;

/**
 * La fiche d'une tâche projet : l'historique (la tâche, puis les échanges) et, dessous, le nouveau message
 * (`HasComposer`) ; à droite, la tâche dans son projet, son temps et les notes (`HasInfoPane`) ; dans le maître, ses
 * passages. « Créer un ticket » ouvre un ticket de TMA à partir d'elle. Le calendrier et l'application ne s'affichent
 * qu'à la demande (« Tout afficher », `HasOnDemand`).
 * Les notes et la modification d'un message s'ouvrent en fenêtres (`HasNotes`).
 */
class ViewTask extends ViewRecord
{
    use HasComposer;
    use HasInfoPane;
    use HasNotes;
    use HasOnDemand;

    protected static string $resource = TaskResource::class;

    protected function sideComponents(Component $time): array
    {
        return TaskInfolist::side($time);
    }

    protected function sideTabLabel(): string
    {
        return __('Tâche');
    }

    protected function sideTabIcon(): string
    {
        return 'heroicon-m-clipboard-document-list';
    }

    public function getTitle(): string
    {
        return $this->getRecord()->number.' — '.$this->getRecord()->title;
    }

    protected function getHeaderActions(): array
    {
        return [
            ...TaskMoveAction::all(),
            Action::make('createTicket')
                ->label(__('Créer un ticket'))
                ->icon('heroicon-m-lifebuoy')
                ->color('gray')
                ->url(fn (ProjectTask $record): string => TicketResource::getUrl('create', ['task' => $record->getKey()]))
                ->visible(fn (ProjectTask $record): bool => $record->isShared() && TicketResource::canCreate()),
            OnDemandAction::make(),
            ActionGroup::make([
                EditAction::make(),
                Action::make('resync')
                    ->label(__('Renvoyer à l’application'))
                    ->icon('heroicon-m-arrow-path')
                    ->visible(fn (ProjectTask $record): bool => Tma::isMaster() && $record->isShared() && TmaGate::allows(auth()->user(), TmaGate::SYNC, $record))
                    ->action(function (ProjectTask $record): void {
                        $record->forceFill(['sync_status' => SyncStatus::Pending])->saveQuietly();
                        rescue(function () use ($record): void {
                            PushToInstance::dispatch($record);
                        });
                        Notification::make()->success()->title(__('Envoi demandé'))->send();
                    }),
                TaskDeleteActions::make(),
            ]),
        ];
    }
}
