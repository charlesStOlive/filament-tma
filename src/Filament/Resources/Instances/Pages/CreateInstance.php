<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Instances\Pages;

use CharlesStOlive\FilamentTma\Filament\Resources\Instances\InstanceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInstance extends CreateRecord
{
    protected static string $resource = InstanceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return InstanceData::withClientType($data);
    }
}
