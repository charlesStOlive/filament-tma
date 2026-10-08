<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Instances\Pages;

use CharlesStOlive\FilamentTma\Filament\Resources\Instances\InstanceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditInstance extends EditRecord
{
    protected static string $resource = InstanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            InstanceResource::pingAction(),
            InstanceResource::usersAction(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return InstanceData::withClientType($data);
    }
}
