<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Instances\Pages;

/** Le client d'une instance est un modèle de l'hôte (`filament-tma.models.client`) : son type suit son id. */
final class InstanceData
{
    /** @param  array<string, mixed>  $data */
    public static function withClientType(array $data): array
    {
        $model = config('filament-tma.models.client');

        if (array_key_exists('client_id', $data)) {
            $data['client_type'] = filled($data['client_id']) && $model ? (new $model)->getMorphClass() : null;
        }

        return $data;
    }
}
