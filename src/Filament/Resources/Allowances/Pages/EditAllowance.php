<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Allowances\Pages;

use CharlesStOlive\FilamentTma\Filament\Resources\Allowances\AllowanceResource;
use CharlesStOlive\FilamentTma\Jobs\PushAllowancesToInstance;
use CharlesStOlive\FilamentTma\Support\Duration;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAllowance extends EditRecord
{
    protected static string $resource = AllowanceResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $byDay = $data['minutes'] % Duration::dayMinutes() === 0;

        return [
            ...$data,
            'volume' => $byDay ? $data['minutes'] / Duration::dayMinutes() : round($data['minutes'] / 60, 2),
            'unit' => $byDay ? Duration::DAYS : Duration::HOURS,
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return AllowanceResource::prepare($data);
    }

    protected function afterSave(): void
    {
        PushAllowancesToInstance::dispatchFor($this->getRecord());
    }
}
