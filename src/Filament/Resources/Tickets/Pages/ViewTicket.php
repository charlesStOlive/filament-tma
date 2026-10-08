<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages;

use CharlesStOlive\FilamentTma\Filament\Actions\MoveAction;
use CharlesStOlive\FilamentTma\Filament\Actions\TimelineActions;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\TicketResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
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
            ActionGroup::make([
                TimelineActions::note(),
                EditAction::make(),
            ]),
        ];
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
