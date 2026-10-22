<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Projects\Pages;

use CharlesStOlive\FilamentTma\Filament\Resources\Projects\ProjectResource;
use Filament\Resources\Pages\ViewRecord;

/**
 * Un projet : son application, sa fourchette, son temps (la somme de celui de ses tâches), ses participants, puis ses
 * tâches. « Modifier le projet » s'ouvre dans un volet glissant ; « Renvoyer à l'application » renvoie ses tâches
 * publiées (maître).
 */
class ViewProject extends ViewRecord
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [ProjectResource::editAction(), ProjectResource::resyncAction()];
    }
}
