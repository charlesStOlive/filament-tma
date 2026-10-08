<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Allowances\Pages;

use CharlesStOlive\FilamentTma\Filament\Resources\Allowances\AllowanceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAllowances extends ListRecords
{
    protected static string $resource = AllowanceResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
