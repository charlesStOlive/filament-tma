<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages;

use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\TicketResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTickets extends ListRecords
{
    protected static string $resource = TicketResource::class;

    protected function getHeaderWidgets(): array
    {
        return [\CharlesStOlive\FilamentTma\Filament\Widgets\AllowanceOverview::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('Nouvelle demande')),
        ];
    }
}
