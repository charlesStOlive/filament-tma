<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Instances\Pages;

use CharlesStOlive\FilamentTma\Filament\Resources\Instances\InstanceResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInstances extends ListRecords
{
    protected static string $resource = InstanceResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }

    /** Affiche la configuration produite par « Configuration de l'application » (`replaceMountedAction`). */
    public function clientEnvAction(): Action
    {
        return InstanceResource::clientEnvAction();
    }
}
