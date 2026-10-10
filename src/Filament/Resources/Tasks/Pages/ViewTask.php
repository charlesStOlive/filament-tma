<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Tasks\Pages;

use CharlesStOlive\FilamentTma\Enums\SyncStatus;
use CharlesStOlive\FilamentTma\Filament\Actions\TaskMoveAction;
use CharlesStOlive\FilamentTma\Filament\Actions\TimelineActions;
use CharlesStOlive\FilamentTma\Filament\Concerns\HasComposer;
use CharlesStOlive\FilamentTma\Filament\Concerns\HasInfoPane;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\Schemas\TaskInfolist;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\TaskResource;
use CharlesStOlive\FilamentTma\Jobs\PushToInstance;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Support\TmaGate;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

/**
 * La fiche d'une tâche projet : sa description et sa chronologie, avec à droite le suivi et le temps passé
 * (`HasInfoPane`) ; dans le maître, ses passages. Sous la chronologie, la zone de réponse (`HasComposer`).
 */
class ViewTask extends ViewRecord
{
    use HasComposer;
    use HasInfoPane;

    protected static string $resource = TaskResource::class;

    protected function sideComponents(): array
    {
        return TaskInfolist::side();
    }

    public function getTitle(): string
    {
        return $this->getRecord()->number.' — '.$this->getRecord()->title;
    }

    protected function getHeaderActions(): array
    {
        return [
            ...TaskMoveAction::all(),
            TimelineActions::agentTime(),
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
            ]),
        ];
    }

    public function shareNoteAction(): Action
    {
        return TimelineActions::shareNote();
    }
}
