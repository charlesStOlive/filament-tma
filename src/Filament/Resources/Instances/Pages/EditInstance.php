<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Instances\Pages;

use CharlesStOlive\FilamentTma\Filament\Resources\Instances\InstanceResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditInstance extends EditRecord
{
    protected static string $resource = InstanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            InstanceResource::setupClientAction(),
            InstanceResource::pingAction(),
            InstanceResource::usersAction(),
            InstanceResource::resyncAction(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return InstanceData::withClientType($data);
    }

    /** Affiche la configuration produite par « Configuration de l'application » (`replaceMountedAction`). */
    public function clientEnvAction(): Action
    {
        return InstanceResource::clientEnvAction();
    }
}
