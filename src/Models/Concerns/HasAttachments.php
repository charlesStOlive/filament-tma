<?php

namespace CharlesStOlive\FilamentTma\Models\Concerns;

use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Les fichiers d'un ticket ou d'une entrée, par medialibrary, sur le disque privé de la config :
 * - `attachments` : les pièces jointes ;
 * - `content` : les images insérées dans le texte riche.
 *
 * Les types acceptés sont ceux de `filament-tma.attachments.accepted_types` (liste blanche, contrôlée sur le
 * contenu réel du fichier par medialibrary). Le téléchargement passe par une route qui vérifie les droits.
 */
trait HasAttachments
{
    use InteractsWithMedia;

    public const ATTACHMENTS_COLLECTION = 'attachments';

    public const CONTENT_COLLECTION = 'content';

    public function registerMediaCollections(): void
    {
        $disk = config('filament-tma.attachments.disk', 'local');
        $types = config('filament-tma.attachments.accepted_types', []);

        $this->addMediaCollection(self::ATTACHMENTS_COLLECTION)
            ->useDisk($disk)
            ->acceptsMimeTypes($types);

        $this->addMediaCollection(self::CONTENT_COLLECTION)
            ->useDisk($disk)
            ->acceptsMimeTypes(array_values(array_filter($types, fn (string $type): bool => str_starts_with($type, 'image/'))));
    }
}
