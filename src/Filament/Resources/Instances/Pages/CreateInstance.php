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

    /** L'application a son propre compte de service dès sa création : on ne choisit jamais un utilisateur. */
    protected function afterCreate(): void
    {
        $this->getRecord()->ensureServiceAccount();
    }

    /** Directement sur sa fiche, où « Configuration de l'application » attend. */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
