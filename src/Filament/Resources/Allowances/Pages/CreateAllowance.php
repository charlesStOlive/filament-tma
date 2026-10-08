<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Allowances\Pages;

use CharlesStOlive\FilamentTma\Filament\Resources\Allowances\AllowanceResource;
use CharlesStOlive\FilamentTma\Jobs\PushAllowancesToInstance;
use Filament\Resources\Pages\CreateRecord;

class CreateAllowance extends CreateRecord
{
    protected static string $resource = AllowanceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return AllowanceResource::prepare($data);
    }

    protected function afterCreate(): void
    {
        PushAllowancesToInstance::dispatchFor($this->getRecord());
    }
}
